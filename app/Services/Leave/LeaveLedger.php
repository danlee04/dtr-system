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

            if (bccomp($result, '0', 3) < 0) {
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
