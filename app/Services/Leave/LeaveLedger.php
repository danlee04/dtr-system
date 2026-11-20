<?php

namespace App\Services\Leave;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads balances from the ledger and records HR's adjustments. Days are
 * decimal strings with three places, added and compared with bcmath, so a
 * balance never drifts by a float's rounding.
 */
final class LeaveLedger
{
    /**
     * Up to three digits and three decimals, with an optional minus. `\z`,
     * not `$`, which would let a trailing newline through to bcmath.
     */
    public const string SIGNED_DAYS = '/^-?\d{1,3}(\.\d{1,3})?\z/';

    /** What takes days off a balance, shown in red on the Balances page; a reversal among them gives its days back. */
    private const array TAKING = [
        LedgerKind::DtrDeduction, LedgerKind::DtrReopened, LedgerKind::Application,
        LedgerKind::Cancellation, LedgerKind::ForcedLeave, LedgerKind::Lapse,
    ];

    /**
     * Each employee's balances at the end of a month, and what the month took
     * from each, for the Balances page (HR, 2026-11-20). A row counts in the
     * month it is for, not the day it was posted: a leave in the month of its
     * first date, a monthly credit or a DTR's deduction in its period ("2026-10",
     * so October's DTR marked final in November is October's), anything else in
     * the month it was posted. A reversal in the same month nets its row out.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, array{balance: string, taken: string}>> employee id → leave type code → days
     */
    public function monthly(array $employeeIds, CarbonImmutable $month): array
    {
        $end = $month->format('Y-m');
        $figures = [];

        foreach ($employeeIds as $id) {
            foreach (LeaveType::tracked() as $type) {
                $figures[$id][$type->value] = ['balance' => '0.000', 'taken' => '0.000'];
            }
        }

        $rows = LeaveLedgerEntry::query()
            ->leftJoin('leave_applications', 'leave_applications.id', '=', 'leave_ledger_entries.leave_application_id')
            ->whereIn('leave_ledger_entries.employee_id', $employeeIds)
            ->toBase()
            ->get(['leave_ledger_entries.employee_id', 'leave_ledger_entries.leave_type', 'leave_ledger_entries.days', 'leave_ledger_entries.kind', 'leave_ledger_entries.period', 'leave_ledger_entries.posted_on', 'leave_applications.date_from']);

        foreach ($rows as $row) {
            if (! isset($figures[$row->employee_id][$row->leave_type])) {
                continue;
            }

            $for = match (true) {
                $row->date_from !== null => substr((string) $row->date_from, 0, 7),
                is_string($row->period) && preg_match('/^\d{4}-\d{2}$/', $row->period) === 1 => $row->period,
                default => substr((string) $row->posted_on, 0, 7),
            };

            if ($for > $end) {
                continue;
            }

            $days = number_format((float) $row->days, 3, '.', '');
            $cell = &$figures[$row->employee_id][$row->leave_type];
            $cell['balance'] = bcadd($cell['balance'], $days, 3);
            $kind = LedgerKind::from($row->kind);

            if ($for === $end && (in_array($kind, self::TAKING, true) || ($kind === LedgerKind::Adjustment && bccomp($days, '0', 3) < 0))) {
                $cell['taken'] = bcsub($cell['taken'], $days, 3);
            }

            unset($cell);
        }

        return $figures;
    }

    /**
     * Every tracked type's balance, VL first, "0.000" where the employee
     * has no rows. With $on, only rows posted on or before that date count.
     *
     * @return array<string, string> leave type code → days
     */
    public function balances(Employee $employee, ?CarbonImmutable $on = null): array
    {
        $totals = $this->rows($employee, $on)
            ->selectRaw('leave_type, sum(days) as total')
            ->groupBy('leave_type')
            ->toBase()
            ->pluck('total', 'leave_type');

        $balances = [];

        foreach (LeaveType::tracked() as $type) {
            $balances[$type->value] = self::days($totals[$type->value] ?? 0);
        }

        return $balances;
    }

    public function balance(Employee $employee, LeaveType $type, ?CarbonImmutable $on = null): string
    {
        return self::days($this->rows($employee, $on)->where('leave_type', $type)->sum('days'));
    }

    /**
     * The rows of one type, or of every type, newest first, each with the
     * balance it left its own type at. Balances are run oldest first, in the
     * order the rows were posted.
     *
     * @return list<array{posted_on: CarbonImmutable, leave_type: LeaveType, entry: string, days: string, balance: string, remarks: ?string}>
     */
    public function history(Employee $employee, ?LeaveType $type = null): array
    {
        $balances = [];
        $history = [];

        $entries = $this->rows($employee, null)
            ->when($type !== null, fn (Builder $query): Builder => $query->where('leave_type', $type))
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $balance = $balances[$entry->leave_type->value] = bcadd($balances[$entry->leave_type->value] ?? '0.000', $entry->days, 3);

            $history[] = [
                'posted_on' => $entry->posted_on->toImmutable(),
                'leave_type' => $entry->leave_type,
                'entry' => $entry->kind->getLabel(),
                'days' => $entry->days,
                'balance' => $balance,
                'remarks' => $entry->remarks,
            ];
        }

        return array_reverse($history);
    }

    /**
     * Adds or deducts days by hand, dated today. The balance is checked with
     * the employee's row locked, so two adjustments at once cannot both take
     * the last day.
     *
     * @throws ValidationException keyed leave_type, days or remarks
     */
    public function adjust(Employee $employee, LeaveType $type, string $days, string $reason): LeaveLedgerEntry
    {
        if (! $type->isTracked()) {
            throw ValidationException::withMessages(['leave_type' => "{$type->getLabel()} has no balance to adjust."]);
        }

        if (preg_match(self::SIGNED_DAYS, $days) !== 1 || bccomp($days, '0', 3) === 0) {
            throw ValidationException::withMessages(['days' => 'Enter a number of days other than zero, with up to three decimals.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['remarks' => 'Give the reason for the adjustment.']);
        }

        app(LeavePosting::class)->catchUp();

        return DB::transaction(function () use ($employee, $type, $days, $reason): LeaveLedgerEntry {
            Employee::query()->whereKey($employee->getKey())->lockForUpdate()->first();

            $result = bcadd($this->balance($employee, $type), $days, 3);

            // Only a deduction is refused: a DTR's Final may already have taken VL below zero (HR, 2026-11-19), and adding days must still go in.
            if (bccomp($days, '0', 3) < 0 && bccomp($result, '0', 3) < 0) {
                throw ValidationException::withMessages(['days' => "That would leave {$type->value} at {$result}."]);
            }

            return $employee->leaveLedgerEntries()->create([
                'leave_type' => $type,
                'posted_on' => today(),
                'days' => bcadd($days, '0', 3),
                'kind' => LedgerKind::Adjustment,
                'remarks' => $reason,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /** @return Builder<LeaveLedgerEntry> */
    private function rows(Employee $employee, ?CarbonImmutable $on): Builder
    {
        return LeaveLedgerEntry::query()
            ->whereBelongsTo($employee)
            ->when($on !== null, fn (Builder $query): Builder => $query->whereDate('posted_on', '<=', $on));
    }

    /** A sum as three-place days: SQLite returns a float, MySQL a string. */
    private static function days(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
