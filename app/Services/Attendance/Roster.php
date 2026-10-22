<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An office's duty roster for one month, as a grid of codes: a schedule's
 * code, OFF for a day off, or blank for no entry (the employee's fixed
 * schedule applies).
 */
final class Roster
{
    /** Typed in a cell for a rostered day off. */
    public const string OFF = 'OFF';

    /**
     * The rows of the office's grid: its active employees, surname first.
     *
     * @return EloquentCollection<int, Employee>
     */
    public function employees(Office $office): EloquentCollection
    {
        return $office->employees()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Every employee of the office and every day of the month, '' where
     * there is no entry.
     *
     * @return array<int, array<int, string>> employee id → day of the month → code
     */
    public function read(Office $office, CarbonImmutable $month): array
    {
        $employees = $this->employees($office);
        $entries = $this->entries($employees->modelKeys(), $month);
        $grid = [];

        foreach ($employees as $employee) {
            foreach (range(1, $month->daysInMonth) as $day) {
                $entry = $entries->get("{$employee->id}.{$day}");

                $grid[$employee->id][$day] = match (true) {
                    $entry === null => '',
                    $entry->schedule === null => self::OFF,
                    default => $entry->schedule->code,
                };
            }
        }

        return $grid;
    }

    /**
     * Saves the cells sent for the office's month, ignoring case and spaces:
     * a code sets that schedule, OFF a day off, and a blank cell removes the
     * entry. Every cell is checked first, and nothing is saved while one is
     * wrong. Only the cells sent change, so a row the page never showed keeps
     * its month; cells for anyone outside the office, or for days the month
     * does not have, are ignored.
     *
     * @param  array<int|string, mixed>  $cells  employee id → day → typed code
     * @return int the number of entries added, changed or removed
     *
     * @throws ValidationException keyed cells.{employee id}.{day}
     */
    public function save(Office $office, CarbonImmutable $month, array $cells): int
    {
        $month = $month->startOfMonth();
        // Every schedule's code, active or not, so an older month still saves.
        $codes = Schedule::query()->pluck('id', 'code');
        $employees = $this->employees($office)
            ->filter(fn (Employee $employee): bool => is_array($cells[$employee->id] ?? null));

        /** @var array<string, array{employee_id: int, date: CarbonImmutable, schedule_id: int|null}> $set */
        $set = [];
        /** @var list<string> $remove */
        $remove = [];
        $errors = [];

        foreach ($employees as $employee) {
            $row = $cells[$employee->id];

            foreach (range(1, $month->daysInMonth) as $day) {
                if (! array_key_exists($day, $row)) {
                    continue;
                }

                $typed = is_scalar($row[$day]) ? trim((string) $row[$day]) : '';
                $code = mb_strtoupper($typed);
                $date = $month->setDay($day);
                $key = "{$employee->id}.{$day}";

                if ($code === '') {
                    $remove[] = $key;
                } elseif ($code === self::OFF || $codes->has($code)) {
                    $set[$key] = [
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'schedule_id' => $code === self::OFF ? null : (int) $codes->get($code),
                    ];
                } else {
                    $errors["cells.{$key}"] = "\"{$typed}\" for {$employee->full_name} on {$date->format('M j')} is not a schedule code.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $entries = $this->entries($employees->modelKeys(), $month);

        // Through the model, one row at a time, so the activity log sees each
        // change, and only where the cell differs from what is stored.
        return DB::transaction(function () use ($set, $remove, $entries): int {
            $changes = 0;

            foreach ($remove as $key) {
                if ($entries->has($key)) {
                    $entries->get($key)->delete();
                    $changes++;
                }
            }

            foreach ($set as $key => $wanted) {
                $entry = $entries->get($key);

                if ($entry === null) {
                    RosterEntry::create($wanted);
                    $changes++;
                } elseif ($entry->schedule_id !== $wanted['schedule_id']) {
                    $entry->update(['schedule_id' => $wanted['schedule_id']]);
                    $changes++;
                }
            }

            return $changes;
        });
    }

    /**
     * The month's entries for these employees, keyed "employee id.day".
     *
     * @param  list<int>  $employeeIds
     * @return Collection<string, RosterEntry>
     */
    private function entries(array $employeeIds, CarbonImmutable $month): Collection
    {
        return RosterEntry::query()
            ->with('schedule')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $month->startOfMonth())
            ->whereDate('date', '<=', $month->endOfMonth())
            ->get()
            ->keyBy(fn (RosterEntry $entry): string => "{$entry->employee_id}.{$entry->date->day}");
    }
}
