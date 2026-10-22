<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keeps an employee's assignments from overlapping, so every date has at most
 * one fixed schedule. Moving someone to a new schedule is one step: the
 * open-ended assignment that began earlier ends the day before.
 */
final class ScheduleAssigner
{
    /**
     * The assignment that overlaps $from–$to ($to null is open-ended). For a
     * new assignment ($editing null), an open-ended one that began before
     * $from is not a conflict: assign() ends it. An edit may overlap nothing.
     */
    public function conflict(Employee $employee, CarbonImmutable $from, ?CarbonImmutable $to, ?ScheduleAssignment $editing = null): ?ScheduleAssignment
    {
        return $employee->scheduleAssignments()
            ->when($editing, fn (Builder $query) => $query->whereKeyNot($editing->getKey()))
            ->when($to, fn (Builder $query) => $query->whereDate('effective_from', '<=', $to))
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $from))
            ->when($editing === null, fn (Builder $query) => $query->whereNot(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->whereDate('effective_from', '<', $from)))
            ->orderBy('effective_from')
            ->first();
    }

    /**
     * Gives the employee $schedule from $from, and ends the open-ended
     * assignment that began before it on the day before $from.
     *
     * @throws InvalidArgumentException when conflict() finds one
     */
    public function assign(Employee $employee, Schedule $schedule, CarbonImmutable $from, ?CarbonImmutable $to): ScheduleAssignment
    {
        return DB::transaction(function () use ($employee, $schedule, $from, $to): ScheduleAssignment {
            $conflict = $this->conflict($employee, $from, $to);

            if ($conflict !== null) {
                throw new InvalidArgumentException("{$employee->full_name} is already on {$conflict->describe()}.");
            }

            // Through the model, one at a time, so the activity log records
            // the end date.
            $employee->scheduleAssignments()
                ->whereNull('effective_to')
                ->whereDate('effective_from', '<', $from)
                ->get()
                ->each(fn (ScheduleAssignment $open) => $open->update(['effective_to' => $from->subDay()]));

            return $employee->scheduleAssignments()->create([
                'schedule_id' => $schedule->getKey(),
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
        });
    }

    /**
     * assign() for each employee, all or none.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<string> one line per employee who could not be assigned;
     *                      nothing is saved unless it is empty
     */
    public function assignAll(Collection $employees, Schedule $schedule, CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        return DB::transaction(function () use ($employees, $schedule, $from, $to): array {
            $problems = [];

            foreach ($employees as $employee) {
                $conflict = $this->conflict($employee, $from, $to);

                if ($conflict !== null) {
                    $problems[] = "{$employee->full_name}: already on {$conflict->describe()}.";
                }
            }

            if ($problems !== []) {
                return $problems;
            }

            foreach ($employees as $employee) {
                $this->assign($employee, $schedule, $from, $to);
            }

            return [];
        });
    }
}
