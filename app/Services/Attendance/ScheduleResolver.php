<?php

namespace App\Services\Attendance;

use App\Enums\DayKind;
use App\Enums\HolidayType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterEntry;
use App\Models\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers "what is this employee's day on this date?" in a fixed order, the
 * first rule that applies winning: not employed, then the roster, then the
 * fixed schedule (with its weekdays and the holidays), then no schedule. The
 * system never guesses a schedule nobody set.
 */
final class ScheduleResolver
{
    /**
     * Every date from $from to $to, in three queries however long the range.
     *
     * @return array<string, ResolvedDay> Y-m-d → day, in date order
     */
    public function days(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $roster = RosterEntry::query()
            ->whereBelongsTo($employee)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with('schedule')
            ->get()
            ->keyBy(fn (RosterEntry $entry): string => $entry->date->toDateString());

        $assignments = ScheduleAssignment::query()
            ->whereBelongsTo($employee)
            ->whereDate('effective_from', '<=', $to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->with('schedule')
            ->get();

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $days = [];

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $days[$date->toDateString()] = $this->resolve($employee, $date, $roster->get($date->toDateString()), $assignments, $holidays->get($date->toDateString()));
        }

        return $days;
    }

    /** @param  Collection<int, ScheduleAssignment>  $assignments */
    private function resolve(Employee $employee, CarbonImmutable $date, ?RosterEntry $entry, Collection $assignments, ?Holiday $holiday): ResolvedDay
    {
        if ($employee->date_hired?->greaterThan($date) || $employee->date_separated?->lessThan($date)) {
            return new ResolvedDay(DayKind::NotEmployed);
        }

        // Holidays never apply to the roster: a nurse rostered on one is expected at work.
        if ($entry !== null) {
            return $entry->schedule === null
                ? new ResolvedDay(DayKind::RestDay)
                : new ResolvedDay(DayKind::WorkDay, $entry->schedule);
        }

        $assignment = $assignments->first(fn (ScheduleAssignment $assignment): bool => $assignment->effective_from->lessThanOrEqualTo($date)
            && ($assignment->effective_to === null || $assignment->effective_to->greaterThanOrEqualTo($date)));

        if ($assignment === null) {
            return new ResolvedDay(DayKind::NoSchedule);
        }

        // The form's checkboxes may store the weekdays as strings.
        if (! in_array($date->isoWeekday(), array_map(intval(...), $assignment->schedule->work_days), true)) {
            return new ResolvedDay(DayKind::RestDay);
        }

        return match (true) {
            $holiday === null => new ResolvedDay(DayKind::WorkDay, $assignment->schedule),
            $holiday->type !== HolidayType::Suspension => new ResolvedDay(DayKind::Holiday),
            $holiday->suspension_starts_at === null => new ResolvedDay(DayKind::Suspended),
            // A timed suspension is still a work day; the DTR cuts its hours.
            default => new ResolvedDay(DayKind::WorkDay, $assignment->schedule),
        };
    }
}
