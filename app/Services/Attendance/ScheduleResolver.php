<?php

namespace App\Services\Attendance;

use App\Enums\DayKind;
use App\Enums\HolidayType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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
        return $this->daysFrom(
            $employee,
            $from,
            $to,
            RosterEntry::query()
                ->whereBelongsTo($employee)
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->with('schedule')
                ->get(),
            ScheduleAssignment::query()
                ->whereBelongsTo($employee)
                ->whereDate('effective_from', '<=', $to)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->with('schedule')
                ->get(),
            Holiday::query()
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->get(),
        );
    }

    /**
     * As days(), from rows already read, so many employees' months can share
     * one read of each (DtrCalculator::computeMany()). Rows of other dates
     * are passed over; the roster and the assignments must be the employee's.
     *
     * @param  Collection<int, RosterEntry>  $roster
     * @param  Collection<int, ScheduleAssignment>  $assignments
     * @param  Collection<int, Holiday>  $holidays
     * @return array<string, ResolvedDay> Y-m-d → day, in date order
     */
    public function daysFrom(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, Collection $roster, Collection $assignments, Collection $holidays): array
    {
        $roster = $roster->keyBy(fn (RosterEntry $entry): string => $entry->date->toDateString());
        $holidays = $holidays->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());
        // Read once, not on every date: each read of a cast attribute parses it again, and a month of a hundred DTRs is three thousand dates.
        $employed = [$employee->date_hired?->toDateString(), $employee->date_separated?->toDateString()];
        $periods = $assignments->map(fn (ScheduleAssignment $assignment): array => [
            'from' => $assignment->effective_from->toDateString(),
            'to' => $assignment->effective_to?->toDateString(),
            'schedule' => $assignment->schedule,
            // The form's checkboxes may store the weekdays as strings.
            'workDays' => array_map(intval(...), $assignment->schedule->work_days),
        ])->all();
        $days = [];

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $day = $date->toDateString();
            $days[$day] = $this->resolve($day, $date->isoWeekday(), $employed, $roster->get($day), $periods, $holidays->get($day));
        }

        return $days;
    }

    /**
     * @param  string  $date  "2026-10-01"; dates as Y-m-d compare as strings
     * @param  array{0: ?string, 1: ?string}  $employed  hired, separated
     * @param  list<array{from: string, to: ?string, schedule: Schedule, workDays: list<int>}>  $periods  the assignments
     */
    private function resolve(string $date, int $weekday, array $employed, ?RosterEntry $entry, array $periods, ?Holiday $holiday): ResolvedDay
    {
        [$hired, $separated] = $employed;

        if (($hired !== null && $hired > $date) || ($separated !== null && $separated < $date)) {
            return new ResolvedDay(DayKind::NotEmployed);
        }

        // Holidays never apply to the roster: a nurse rostered on one is expected at work.
        if ($entry !== null) {
            return $entry->schedule === null
                ? new ResolvedDay(DayKind::RestDay)
                : new ResolvedDay(DayKind::WorkDay, $entry->schedule);
        }

        $period = array_find($periods, fn (array $period): bool => $period['from'] <= $date && ($period['to'] === null || $period['to'] >= $date));

        if ($period === null) {
            return new ResolvedDay(DayKind::NoSchedule);
        }

        if (! in_array($weekday, $period['workDays'], true)) {
            return new ResolvedDay(DayKind::RestDay);
        }

        return match (true) {
            $holiday === null => new ResolvedDay(DayKind::WorkDay, $period['schedule']),
            $holiday->type !== HolidayType::Suspension => new ResolvedDay(DayKind::Holiday),
            $holiday->suspension_starts_at === null => new ResolvedDay(DayKind::Suspended),
            // A timed suspension is still a work day; the DTR cuts its hours.
            default => new ResolvedDay(DayKind::WorkDay, $period['schedule']),
        };
    }
}
