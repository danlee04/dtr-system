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
 * Files, approves and cancels leave. Filing deducts at once: the days are
 * counted against the schedule, charged to the balances in order, and the
 * rest is leave without pay. Cancelling gives back exactly what was drawn.
 * Both run with the employee's row locked, after posting whatever is due,
 * so no balance is read stale or drawn twice.
 */
final class LeaveApplications
{
    public function __construct(
        private LeaveLedger $ledger,
        private LeavePosting $posting,
        private LeaveDayCounter $counter,
        private LeaveCharger $charger,
    ) {}

    /** What the application would cost now, for the form to show before it is filed. */
    public function preview(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage): Charge
    {
        $start = $this->start();
        $this->posting->catchUp();

        return $this->charger->charge($type, $this->counter->count($employee, $type, $from, $to, $coverage, $start), $this->ledger->balances($employee));
    }

    /**
     * @param  array<string, mixed>  $data  leave_type, date_from, date_to, coverage, and optionally detail, detail_text, commutation_requested
     *
     * @throws ValidationException keyed by the field at fault, or employee_id
     */
    public function file(Employee $employee, array $data): LeaveApplication
    {
        if (! $employee->is_active || ! $employee->employment_status->earnsLeaveCredits()) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} earns no leave credits."]);
        }

        $start = $this->start();
        $type = self::option(LeaveType::class, $data['leave_type'] ?? null)
            ?? throw ValidationException::withMessages(['leave_type' => 'Choose a leave type.']);
        $from = $this->date($data, 'date_from');
        $to = $this->date($data, 'date_to');
        $coverage = self::option(LeaveCoverage::class, $data['coverage'] ?? LeaveCoverage::WholeDay)
            ?? throw ValidationException::withMessages(['coverage' => 'Choose whole day or a half.']);
        [$detail, $detailText] = $this->details($type, $data);

        $this->posting->catchUp();

        return DB::transaction(function () use ($employee, $start, $type, $from, $to, $coverage, $detail, $detailText, $data): LeaveApplication {
            Employee::query()->whereKey($employee->getKey())->lockForUpdate()->first();

            $this->refuseOverlap($employee, $from, $to, $coverage);

            $charge = $this->charger->charge(
                $type,
                $this->counter->count($employee, $type, $from, $to, $coverage, $start),
                $this->ledger->balances($employee),
            );

            $application = $employee->leaveApplications()->create([
                'leave_type' => $type,
                'date_from' => $from,
                'date_to' => $to,
                'coverage' => $coverage,
                'detail' => $detail,
                'detail_text' => $detailText,
                'commutation_requested' => (bool) ($data['commutation_requested'] ?? false),
                'days' => $charge->days,
                'days_with_pay' => $charge->withPay,
                'days_without_pay' => $charge->withoutPay,
                'breakdown' => $charge->breakdown,
                'status' => LeaveApplicationStatus::Pending,
                'filed_by_user_id' => auth()->id(),
            ]);

            foreach ($charge->draws as $code => $days) {
                $application->ledgerEntries()->create([
                    'employee_id' => $employee->id,
                    'leave_type' => LeaveType::from($code),
                    'posted_on' => today(),
                    'days' => bcsub('0', $days, 3),
                    'kind' => LedgerKind::Application,
                    'remarks' => "#{$application->id}, {$application->dates()}",
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $this->posting->correctCredits($employee, $this->monthsWithoutPay($application));

            return $application;
        });
    }

    /** @throws ValidationException keyed status */
    public function approve(LeaveApplication $application): void
    {
        if ($application->status !== LeaveApplicationStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only a pending application can be approved.']);
        }

        $application->update([
            'status' => LeaveApplicationStatus::Approved,
            'approved_at' => now(),
            'approved_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Gives back every day the application drew, dated today. SPL and
     * Wellness from a year that has closed already lapsed, so they are not
     * given back; VL and SL carry over and always are.
     *
     * @throws ValidationException keyed status or cancel_reason
     */
    public function cancel(LeaveApplication $application, string $reason): void
    {
        if ($application->status === LeaveApplicationStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'This application is already cancelled.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['cancel_reason' => 'Give the reason for cancelling.']);
        }

        $this->posting->catchUp();

        DB::transaction(function () use ($application, $reason): void {
            Employee::query()->whereKey($application->employee_id)->lockForUpdate()->first();

            // A second click that waited on the lock finds it already cancelled.
            if ($application->refresh()->status === LeaveApplicationStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'This application is already cancelled.']);
            }

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
                    'remarks' => "#{$application->id} cancelled: ".trim($reason),
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $application->update([
                'status' => LeaveApplicationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'cancel_reason' => trim($reason),
            ]);

            $this->posting->correctCredits($application->employee, $this->monthsWithoutPay($application));
        });
    }

    /**
     * The months of the application's days without pay, which lower those
     * months' credits.
     *
     * @return list<string> Y-m
     */
    private function monthsWithoutPay(LeaveApplication $application): array
    {
        $months = [];

        foreach ($application->breakdown as $date => $day) {
            if (bccomp($day['without_pay'], '0', 3) > 0) {
                $months[] = substr($date, 0, 7);
            }
        }

        return array_values(array_unique($months));
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
     * An employee's applications that are not cancelled may not share a
     * date, except a first half and a second half of the same one.
     */
    private function refuseOverlap(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage): void
    {
        $others = LeaveApplication::query()
            ->whereBelongsTo($employee)
            ->where('status', '!=', LeaveApplicationStatus::Cancelled)
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
