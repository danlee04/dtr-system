<?php

namespace App\Services\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The balances HR copies from its own records on the day the system takes
 * over. They are the only ledger rows that can change: a typo is fixed by
 * saving again, until the employee has any other row. From then on the
 * opening row stays as it was, so what was charged against it still adds up,
 * and a change posts an adjustment for the difference, dated today (HR,
 * 2026-11-23); the grid reads the opening with those corrections in.
 */
final class OpeningBalances
{
    /**
     * The grid's columns. FL holds the VL and forced-leave days already taken
     * in the start year, for the first 31 December; it is not a balance.
     *
     * @var array<string, string> leave type code → column heading
     */
    public const array COLUMNS = ['VL' => 'VL', 'SL' => 'SL', 'SPL' => 'SPL', 'WL' => 'Wellness', 'FL' => 'VL/FL taken this year'];

    /** @var array<string, string> leave type code → the most a year gives */
    public const array LIMITS = ['SPL' => '3', 'WL' => '5'];

    /** Up to three digits and three decimals, never negative. */
    private const string DAYS = '/^\d{1,3}(\.\d{1,3})?\z/';

    /**
     * The active employees who earn credits, of the office or of every office
     * when there is none, surname first.
     *
     * @return EloquentCollection<int, Employee>
     */
    public function employees(?Office $office): EloquentCollection
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return Employee::query()
            ->when($office, fn (Builder $query, Office $office): Builder => $query->whereBelongsTo($office))
            ->whereIn('employment_status', $earning)
            ->where('is_active', true)
            ->with('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array<int, array<string, string>> employee id → leave type code → days, '' where there is no opening row
     */
    public function read(?Office $office): array
    {
        $employees = $this->employees($office);
        $openings = $this->openings($employees->modelKeys());
        $corrections = $this->corrections($employees->modelKeys());
        $grid = [];

        foreach ($employees as $employee) {
            foreach (array_keys(self::COLUMNS) as $code) {
                $grid[$employee->id][$code] = $this->current($openings, $corrections, $employee->id, $code) ?? '';
            }
        }

        return $grid;
    }

    /**
     * Saves the cells sent: a number sets the opening row, a blank or zero
     * removes it; for a locked employee, a change posts an adjustment for the
     * difference instead, refused when it would take the balance below zero,
     * and "taken this year", not a balance, no longer changes. Every cell is
     * checked first, and nothing is saved while one is wrong. Cells that did
     * not change are skipped; cells for anyone outside the office (when there
     * is one), inactive, or who earns no credits, are ignored.
     *
     * @param  array<int|string, mixed>  $rows  employee id → leave type code → typed days
     * @return int the number of rows added, changed or removed, adjustments included
     *
     * @throws ValidationException keyed rows.{employee id}.{code}
     */
    public function save(?Office $office, CarbonImmutable $start, array $rows): int
    {
        $employees = $this->employees($office)
            ->filter(fn (Employee $employee): bool => is_array($rows[$employee->id] ?? null));
        $openings = $this->openings($employees->modelKeys());
        $corrections = $this->corrections($employees->modelKeys());
        $locked = $this->locked($employees->modelKeys());

        /** @var list<array{Employee, string, string|null, LeaveLedgerEntry|null}> $changes */
        $changes = [];
        /** @var list<array{Employee, string, string, string}> $fixes employee, code, current, wanted */
        $fixes = [];
        $errors = [];

        foreach ($employees as $employee) {
            foreach (self::COLUMNS as $code => $heading) {
                if (! array_key_exists($code, $rows[$employee->id])) {
                    continue;
                }

                $value = $rows[$employee->id][$code];
                $typed = is_scalar($value) ? trim((string) $value) : '';
                $cell = "rows.{$employee->id}.{$code}";

                if ($typed !== '' && preg_match(self::DAYS, $typed) !== 1) {
                    $errors[$cell] = "\"{$typed}\" for {$employee->full_name}, {$heading}, is not a number of days with up to three decimals.";

                    continue;
                }

                $days = $typed === '' || bccomp($typed, '0', 3) === 0 ? null : bcadd($typed, '0', 3);

                if ($days !== null && isset(self::LIMITS[$code]) && bccomp($days, self::LIMITS[$code], 3) > 0) {
                    $errors[$cell] = "{$employee->full_name}'s {$heading} cannot be more than ".self::LIMITS[$code].'.';

                    continue;
                }

                $existing = $openings->get("opening:{$employee->id}:{$code}");

                if (! in_array($employee->id, $locked, true)) {
                    if ($existing?->days !== $days) {
                        $changes[] = [$employee, $code, $days, $existing];
                    }

                    continue;
                }

                $current = $this->current($openings, $corrections, $employee->id, $code) ?? '0.000';
                $wanted = $days ?? '0.000';

                if (bccomp($current, $wanted, 3) === 0) {
                    continue;
                }

                if (! LeaveType::from($code)->isTracked()) {
                    $errors[$cell] = "{$employee->full_name}'s {$heading} can no longer change: credits or leave have posted since.";

                    continue;
                }

                $fixes[] = [$employee, $code, $current, $wanted];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Through the model, one row at a time, so the activity log keeps
        // every value HR typed and every value it replaced.
        return DB::transaction(function () use ($changes, $fixes, $start): int {
            // As LeaveLedger::adjust: only a deduction is refused, checked with the employee locked.
            $errors = [];

            foreach ($fixes as [$employee, $code, $current, $wanted]) {
                if (bccomp($wanted, $current, 3) < 0) {
                    Employee::query()->whereKey($employee->getKey())->lockForUpdate()->first();
                    $result = bcadd(app(LeaveLedger::class)->balance($employee, LeaveType::from($code)), bcsub($wanted, $current, 3), 3);

                    if (bccomp($result, '0', 3) < 0) {
                        $errors["rows.{$employee->id}.{$code}"] = "Correcting {$employee->full_name}'s {$code} to {$wanted} would leave it at {$result}.";
                    }
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($fixes as [$employee, $code, $current, $wanted]) {
                $employee->leaveLedgerEntries()->create([
                    'leave_type' => LeaveType::from($code),
                    'posted_on' => today(),
                    'days' => bcsub($wanted, $current, 3),
                    'kind' => LedgerKind::Adjustment,
                    // Marks it a correction of the opening, which the grid reads in.
                    'posting_key' => "opening-fix:{$employee->id}:{$code}:".Str::ulid(),
                    'remarks' => "Opening balance corrected from {$current} to {$wanted}",
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            foreach ($changes as [$employee, $code, $days, $existing]) {
                match (true) {
                    $days === null => $existing->delete(),
                    $existing !== null => $existing->update(['days' => $days]),
                    default => $employee->leaveLedgerEntries()->create([
                        'leave_type' => LeaveType::from($code),
                        'posted_on' => $start->subDay(),
                        'days' => $days,
                        'kind' => LedgerKind::Opening,
                        'posting_key' => "opening:{$employee->id}:{$code}",
                        'created_by_user_id' => auth()->id(),
                    ]),
                };
            }

            return count($changes) + count($fixes);
        });
    }

    /**
     * The employees whose opening balances are in use: they have an opening
     * row and a row of any other kind after it, so a change posts an
     * adjustment instead. Without an opening row, a first save is still
     * accepted.
     *
     * @param  list<int>  $employeeIds
     * @return list<int>
     */
    public function locked(array $employeeIds): array
    {
        $withOpening = $this->openings($employeeIds)->pluck('employee_id')->unique();

        return LeaveLedgerEntry::query()
            ->whereIn('employee_id', $withOpening->all())
            ->where('kind', '!=', LedgerKind::Opening)
            ->distinct()
            ->pluck('employee_id')
            ->all();
    }

    /**
     * What the corrections of each opening add up to.
     *
     * @param  list<int>  $employeeIds
     * @return array<string, string> "employee id:code" → days
     */
    private function corrections(array $employeeIds): array
    {
        return LeaveLedgerEntry::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('posting_key', 'like', 'opening-fix:%')
            ->get()
            ->groupBy(fn (LeaveLedgerEntry $row): string => "{$row->employee_id}:{$row->leave_type->value}")
            ->map(fn (EloquentCollection $rows): string => $rows->reduce(fn (string $sum, LeaveLedgerEntry $row): string => bcadd($sum, $row->days, 3), '0.000'))
            ->all();
    }

    /**
     * The opening with its corrections in, or null when there is neither.
     *
     * @param  EloquentCollection<string, LeaveLedgerEntry>  $openings
     * @param  array<string, string>  $corrections
     */
    private function current(EloquentCollection $openings, array $corrections, int $employeeId, string $code): ?string
    {
        $opening = $openings->get("opening:{$employeeId}:{$code}")?->days;
        $correction = $corrections["{$employeeId}:{$code}"] ?? null;

        return $opening === null && $correction === null ? null : bcadd($opening ?? '0', $correction ?? '0', 3);
    }

    /**
     * @param  list<int>  $employeeIds
     * @return EloquentCollection<string, LeaveLedgerEntry> keyed by posting key
     */
    private function openings(array $employeeIds): EloquentCollection
    {
        return LeaveLedgerEntry::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('kind', LedgerKind::Opening)
            ->get()
            ->keyBy('posting_key');
    }
}
