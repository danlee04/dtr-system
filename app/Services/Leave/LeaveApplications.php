<?php

namespace App\Services\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveDetail;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Files, changes, approves, disapproves and cancels leave. Filing deducts at
 * once: the days are counted against the schedule, charged to the balances in
 * order, and the rest is leave without pay. A change while pending charges
 * it again in place. Disapproving or cancelling gives back exactly what was
 * drawn. Each runs with the employee's row locked, after posting whatever is
 * due, so no balance is read stale or drawn twice.
 */
final class LeaveApplications
{
    public function __construct(
        private LeaveLedger $ledger,
        private LeavePosting $posting,
        private LeaveDayCounter $counter,
        private LeaveCharger $charger,
    ) {}

    /**
     * What the application would cost now, for the form to show before it is
     * filed. For a pending one being changed, what it draws now counts as
     * still on hand.
     */
    public function preview(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage, ?LeaveApplication $changing = null): Charge
    {
        $start = $this->start();
        $this->posting->catchUp();
        $balances = $this->ledger->balances($employee);

        foreach ($changing?->ledgerEntries()->where('kind', LedgerKind::Application)->get() ?? [] as $row) {
            $balances[$row->leave_type->value] = bcsub($balances[$row->leave_type->value], $row->days, 3);
        }

        return $this->charger->charge($type, $this->counter->count($employee, $type, $from, $to, $coverage, $start), $balances);
    }

    /**
     * @param  array<string, mixed>  $data  leave_type, date_from, date_to, coverage, and optionally detail, detail_text, commutation_requested, division_head
     *
     * @throws ValidationException keyed by the field at fault, or employee_id
     */
    public function file(Employee $employee, array $data): LeaveApplication
    {
        if (! $employee->is_active || ! $employee->employment_status->earnsLeaveCredits()) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} earns no leave credits."]);
        }

        $start = $this->start();
        $fields = $this->fields($data);

        $this->posting->catchUp();

        return DB::transaction(function () use ($employee, $start, $fields): LeaveApplication {
            Employee::query()->whereKey($employee->getKey())->lockForUpdate()->first();

            $this->refuseOverlap($employee, $fields['date_from'], $fields['date_to'], $fields['coverage']);

            [$charge, $charged] = $this->charge($employee, $fields, $start);

            $application = $employee->leaveApplications()->create([
                ...$fields,
                ...$charged,
                'status' => LeaveApplicationStatus::Pending,
                'filed_by_user_id' => auth()->id(),
            ]);

            $this->draw($application, $charge, today());
            $this->posting->correctCredits($application);

            return $application;
        });
    }

    /**
     * Changes a pending application and charges it again in place: the rows
     * it drew are replaced, still dated the day it was filed, so a correction
     * leaves no cancellation and refiling behind (HR, 2026-11-23). Its form
     * is to be printed again.
     *
     * @param  array<string, mixed>  $data  as for file()
     *
     * @throws ValidationException keyed by the field at fault, or status
     */
    public function update(LeaveApplication $application, array $data): void
    {
        $start = $this->start();
        $fields = $this->fields($data);

        $this->posting->catchUp();

        DB::transaction(function () use ($application, $start, $fields): void {
            $employee = Employee::withTrashed()->whereKey($application->employee_id)->lockForUpdate()->firstOrFail();

            if ($application->refresh()->status !== LeaveApplicationStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only a pending application can be changed.']);
            }

            $this->refuseOverlap($employee, $fields['date_from'], $fields['date_to'], $fields['coverage'], except: $application);

            $before = $application->breakdown;
            $drawn = $application->ledgerEntries()->where('kind', LedgerKind::Application)->get();
            $postedOn = $drawn->first()->posted_on ?? today();
            // One by one, so each reaches the activity log.
            $drawn->each->delete();

            [$charge, $charged] = $this->charge($employee, $fields, $start);

            $application->update([...$fields, ...$charged]);
            $this->draw($application, $charge, $postedOn);
            $this->posting->correctCredits($application, $before);
        });
    }

    /** @throws ValidationException keyed status */
    public function approve(LeaveApplication $application): void
    {
        // Locked and re-read, so an approval from a screen opened before a
        // cancel cannot turn the cancelled application back to approved.
        DB::transaction(function () use ($application): void {
            Employee::withTrashed()->whereKey($application->employee_id)->lockForUpdate()->first();

            if ($application->refresh()->status !== LeaveApplicationStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only a pending application can be approved.']);
            }

            $application->update([
                'status' => LeaveApplicationStatus::Approved,
                'approved_at' => now(),
                'approved_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Gives back every day the application drew, dated today; see giveBack().
     *
     * @throws ValidationException keyed status or cancel_reason
     */
    public function cancel(LeaveApplication $application, string $reason): void
    {
        self::refuseClosed($application);

        if (blank($reason)) {
            throw ValidationException::withMessages(['cancel_reason' => 'Give the reason for cancelling.']);
        }

        $this->posting->catchUp();

        DB::transaction(function () use ($application, $reason): void {
            Employee::withTrashed()->whereKey($application->employee_id)->lockForUpdate()->first();

            // A second click that waited on the lock finds it already cancelled.
            self::refuseClosed($application->refresh());

            $this->giveBack($application, "#{$application->id} cancelled: ".trim($reason));

            $application->update([
                'status' => LeaveApplicationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'cancel_reason' => trim($reason),
            ]);

            $this->posting->correctCredits($application);
        });
    }

    /**
     * HR does not approve a pending application: its days are given back, as
     * on a cancellation, and the reason goes into 7.D of the form (HR,
     * 2026-11-23).
     *
     * @throws ValidationException keyed status or disapproval_reason
     */
    public function disapprove(LeaveApplication $application, string $reason): void
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['disapproval_reason' => 'Give the reason for disapproving.']);
        }

        $this->posting->catchUp();

        DB::transaction(function () use ($application, $reason): void {
            Employee::withTrashed()->whereKey($application->employee_id)->lockForUpdate()->first();

            if ($application->refresh()->status !== LeaveApplicationStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only a pending application can be disapproved.']);
            }

            $this->giveBack($application, "#{$application->id} disapproved: ".trim($reason));

            $application->update([
                'status' => LeaveApplicationStatus::Disapproved,
                'disapproved_at' => now(),
                'disapproved_by_user_id' => auth()->id(),
                'disapproval_reason' => trim($reason),
            ]);

            $this->posting->correctCredits($application);
        });
    }

    /** @throws ValidationException keyed status, when the application already gave its days back */
    private static function refuseClosed(LeaveApplication $application): void
    {
        if (! $application->status->stands()) {
            throw ValidationException::withMessages(['status' => 'This application is already '.mb_strtolower($application->status->getLabel()).'.']);
        }
    }

    /**
     * Posts back each day the application drew, dated today. SPL and Wellness
     * from a year that has closed already lapsed, so they are not given back;
     * VL and SL carry over and always are.
     */
    private function giveBack(LeaveApplication $application, string $remarks): void
    {
        $closed = $this->posting->isYearClosed($application->date_from->year);

        foreach ($application->ledgerEntries()->where('kind', LedgerKind::Application)->get() as $row) {
            if ($closed && in_array($row->leave_type, [LeaveType::SpecialPrivilege, LeaveType::Wellness], true)) {
                continue;
            }

            $application->ledgerEntries()->create([
                'employee_id' => $application->employee_id,
                'leave_type' => $row->leave_type,
                'posted_on' => today(),
                'days' => bcsub('0', $row->days, 3),
                'kind' => LedgerKind::Cancellation,
                'remarks' => $remarks,
                'created_by_user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * The application's own fields, checked: what file() and update() save.
     *
     * @param  array<string, mixed>  $data
     * @return array{leave_type: LeaveType, date_from: CarbonImmutable, date_to: CarbonImmutable, coverage: LeaveCoverage, detail: ?LeaveDetail, detail_text: ?string, commutation_requested: bool, division_head: ?string}
     */
    private function fields(array $data): array
    {
        $type = self::option(LeaveType::class, $data['leave_type'] ?? null)
            ?? throw ValidationException::withMessages(['leave_type' => 'Choose a leave type.']);
        $coverage = self::option(LeaveCoverage::class, $data['coverage'] ?? LeaveCoverage::WholeDay)
            ?? throw ValidationException::withMessages(['coverage' => 'Choose whole day or a half.']);
        [$detail, $detailText] = $this->details($type, $data);

        return [
            'leave_type' => $type,
            'date_from' => $this->date($data, 'date_from'),
            'date_to' => $this->date($data, 'date_to'),
            'coverage' => $coverage,
            'detail' => $detail,
            'detail_text' => $detailText,
            'commutation_requested' => (bool) ($data['commutation_requested'] ?? false),
            'division_head' => $this->divisionHead($data),
        ];
    }

    /**
     * The days counted and charged against the balances now, and the
     * application's columns that record it.
     *
     * @param  array{leave_type: LeaveType, date_from: CarbonImmutable, date_to: CarbonImmutable, coverage: LeaveCoverage}  $fields
     * @return array{Charge, array<string, mixed>}
     */
    private function charge(Employee $employee, array $fields, CarbonImmutable $start): array
    {
        $balances = $this->ledger->balances($employee);
        $charge = $this->charger->charge(
            $fields['leave_type'],
            $this->counter->count($employee, $fields['leave_type'], $fields['date_from'], $fields['date_to'], $fields['coverage'], $start),
            $balances,
        );

        return [$charge, [
            'days' => $charge->days,
            'days_with_pay' => $charge->withPay,
            'days_without_pay' => $charge->withoutPay,
            'breakdown' => $charge->breakdown,
            // For 7.A of the printed form, which certifies VL and SL only.
            'credits_at_filing' => ['VL' => $balances['VL'], 'SL' => $balances['SL']],
        ]];
    }

    /** Posts what the application draws from each balance. */
    private function draw(LeaveApplication $application, Charge $charge, mixed $postedOn): void
    {
        foreach ($charge->draws as $code => $days) {
            $application->ledgerEntries()->create([
                'employee_id' => $application->employee_id,
                'leave_type' => LeaveType::from($code),
                'posted_on' => $postedOn,
                'days' => bcsub('0', $days, 3),
                'kind' => LedgerKind::Application,
                'remarks' => "#{$application->id}, {$application->dates()}",
                'created_by_user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * A choice as the enum, whether the form sent the case (Filament casts
     * enum options) or its value.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private static function option(string $enum, mixed $value): ?\BackedEnum
    {
        return $value instanceof $enum ? $value : $enum::tryFrom((string) $value);
    }

    private function start(): CarbonImmutable
    {
        return LeavePosting::configuredStart()
            ?? throw ValidationException::withMessages(['date_from' => 'Set LEAVE_START_DATE in .env first.']);
    }

    /** @param  array<string, mixed>  $data */
    private function date(array $data, string $key): CarbonImmutable
    {
        if (blank($data[$key] ?? null)) {
            throw ValidationException::withMessages([$key => 'Enter the date.']);
        }

        try {
            return CarbonImmutable::parse($data[$key])->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages([$key => 'Enter a valid date.']);
        }
    }

    /**
     * The details of item 6.B: a choice that fits the type, and the text the
     * form asks for (the country, or the illness).
     *
     * @param  array<string, mixed>  $data
     * @return array{LeaveDetail|null, string|null}
     */
    private function details(LeaveType $type, array $data): array
    {
        $detail = null;

        if (filled($data['detail'] ?? null)) {
            $detail = self::option(LeaveDetail::class, $data['detail']);

            if ($detail === null || ! in_array($detail, LeaveDetail::forType($type), true)) {
                throw ValidationException::withMessages(['detail' => "That detail does not apply to {$type->getLabel()}."]);
            }
        }

        $text = trim((string) ($data['detail_text'] ?? ''));
        $text = $text === '' ? null : $text;

        if ($text === null && ($detail?->needsText() || $type === LeaveType::SpecialLeaveBenefitsForWomen)) {
            throw ValidationException::withMessages(['detail_text' => $detail === LeaveDetail::Abroad ? 'Name the country.' : 'Name the illness.']);
        }

        if ($text !== null && mb_strlen($text) > 255) {
            throw ValidationException::withMessages(['detail_text' => 'Keep it under 255 characters.']);
        }

        return [$detail, $text];
    }

    /**
     * Typed by hand on each application for now: who heads the employee's
     * division and signs 7.B.
     *
     * @param  array<string, mixed>  $data
     */
    private function divisionHead(array $data): ?string
    {
        $name = trim((string) ($data['division_head'] ?? ''));

        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['division_head' => 'Keep it under 255 characters.']);
        }

        return $name === '' ? null : $name;
    }

    /**
     * An employee's applications that stand (not cancelled or disapproved) may
     * not share a date, except a first half and a second half of the same one.
     * $except is the application being changed.
     */
    private function refuseOverlap(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage, ?LeaveApplication $except = null): void
    {
        $others = LeaveApplication::query()
            ->whereBelongsTo($employee)
            ->whereIn('status', LeaveApplicationStatus::standing())
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->whereDate('date_from', '<=', $to)
            ->whereDate('date_to', '>=', $from)
            ->get();

        foreach ($others as $other) {
            $otherHalf = $from->equalTo($to)
                && $other->date_from->equalTo($other->date_to)
                && $coverage !== LeaveCoverage::WholeDay
                && $other->coverage !== LeaveCoverage::WholeDay
                && $coverage !== $other->coverage;

            if (! $otherHalf) {
                throw ValidationException::withMessages(['date_from' => "Overlaps leave #{$other->id}, {$other->dates()}."]);
            }
        }
    }
}
