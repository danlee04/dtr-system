<?php

namespace App\Services\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The balances HR copies from its own records on the day the system takes
 * over, one office at a time. They are the only ledger rows that can change:
 * a typo is fixed by saving again, until the employee has any other row.
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
    private const array LIMITS = ['SPL' => '3', 'WL' => '5'];

    /** Up to three digits and three decimals, never negative. */
    private const string DAYS = '/^\d{1,3}(\.\d{1,3})?\z/';

    /**
     * The office's active employees who earn credits, surname first.
     *
     * @return EloquentCollection<int, Employee>
     */
    public function employees(Office $office): EloquentCollection
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return $office->employees()
            ->whereIn('employment_status', $earning)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array<int, array<string, string>> employee id → leave type code → days, '' where there is no opening row
     */
    public function read(Office $office): array
    {
        $employees = $this->employees($office);
        $openings = $this->openings($employees->modelKeys());
        $grid = [];

        foreach ($employees as $employee) {
            foreach (array_keys(self::COLUMNS) as $code) {
                $grid[$employee->id][$code] = $openings->get("opening:{$employee->id}:{$code}")->days ?? '';
            }
        }

        return $grid;
    }

    /**
     * Saves the cells sent: a number sets the opening row, a blank or zero
     * removes it. Every cell is checked first, and nothing is saved while one
     * is wrong. Cells that did not change are skipped, so a locked employee's
     * row can be sent back as it was; cells for anyone outside the office, or
     * who earns no credits, are ignored.
     *
     * @param  array<int|string, mixed>  $rows  employee id → leave type code → typed days
     * @return int the number of rows added, changed or removed
     *
     * @throws ValidationException keyed rows.{employee id}.{code}
     */
    public function save(Office $office, CarbonImmutable $start, array $rows): int
    {
        $employees = $this->employees($office)
            ->filter(fn (Employee $employee): bool => is_array($rows[$employee->id] ?? null));
        $openings = $this->openings($employees->modelKeys());
        $locked = LeaveLedgerEntry::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('kind', '!=', LedgerKind::Opening)
            ->distinct()
            ->pluck('employee_id');

        /** @var list<array{Employee, string, string|null, LeaveLedgerEntry|null}> $changes */
        $changes = [];
        $errors = [];

        foreach ($employees as $employee) {
            $hasOpening = $openings->contains(fn (LeaveLedgerEntry $row): bool => $row->employee_id === $employee->id);

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

                if ($existing?->days === $days) {
                    continue;
                }

                if ($hasOpening && $locked->contains($employee->id)) {
                    $errors[$cell] = "{$employee->full_name} already has credits or leave after the opening balance; use an adjustment on the Leave tab.";

                    continue;
                }

                $changes[] = [$employee, $code, $days, $existing];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Through the model, one row at a time, so the activity log keeps
        // every value HR typed and every value it replaced.
        return DB::transaction(function () use ($changes, $start): int {
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

            return count($changes);
        });
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
