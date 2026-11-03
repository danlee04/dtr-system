<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\HolidayType;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Models\DayException;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Late and undertime for each date of an uploaded DTR, against the schedule
 * the app has for that date. Nothing is stored: the figures follow the
 * schedules, holidays and leave as they stand, so fixing a schedule fixes
 * the month without uploading it again.
 *
 * HR's rule for now: a day with any tap is a day at work. A missing tap costs
 * nothing and is listed for HR to look at; late is read from the arrivals
 * there are, and undertime from the departures. A work day with no tap at
 * all, and no leave, is absent.
 *
 * The DTR's columns are the slots: A.M. Arrival and Departure for the first
 * half of the day, P.M. for the second. O.T. is not read.
 */
final class DtrCalculator
{
    /** What each tap is called, in the order of the day. */
    private const TAPS = [
        2 => [['time in', 'lunch out'], ['lunch in', 'time out']],
        1 => [['time in', 'time out']],
    ];

    public function __construct(private ScheduleResolver $resolver) {}

    public function compute(DtrUpload $upload): ComputedDtr
    {
        $employee = $upload->employee;

        if ($employee === null) {
            return new ComputedDtr([], true);
        }

        $from = $upload->period_start->toImmutable();
        $to = $upload->period_end->toImmutable();
        $rows = $upload->days->keyBy(fn (DtrDay $day): string => $day->date->toDateString());

        // Leave, OBs and travel orders, each with what it makes of the days it covers.
        $covers = LeaveApplication::query()
            ->whereBelongsTo($employee)
            ->where('status', '!=', LeaveApplicationStatus::Cancelled)
            ->whereDate('date_from', '<=', $to)
            ->whereDate('date_to', '>=', $from)
            ->get()
            ->map(fn (LeaveApplication $application): array => [$application, AttendanceStatus::Leave])
            ->concat(DayException::query()
                ->whereBelongsTo($employee)
                ->whereDate('date_from', '<=', $to)
                ->whereDate('date_to', '>=', $from)
                ->get()
                ->map(fn (DayException $exception): array => [$exception, AttendanceStatus::from($exception->type->value)]));

        $suspensions = Holiday::query()
            ->where('type', HolidayType::Suspension)
            ->whereNotNull('suspension_starts_at')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => self::minute($holiday->suspension_starts_at)]);

        $days = [];

        foreach ($this->resolver->days($employee, $from, $to) as $date => $resolved) {
            $days[$date] = $this->day(CarbonImmutable::parse($date), $resolved, $rows->get($date), $covers, $suspensions->get($date));
        }

        return new ComputedDtr($days, $employee->employment_status->earnsLeaveCredits());
    }

    /** @param  SupportCollection<int, array{0: LeaveApplication|DayException, 1: AttendanceStatus}>  $covers */
    private function day(CarbonImmutable $date, ResolvedDay $resolved, ?DtrDay $row, SupportCollection $covers, ?int $suspendedAt): ComputedDay
    {
        if ($resolved->kind !== DayKind::WorkDay) {
            return new ComputedDay(AttendanceStatus::from($resolved->kind->value));
        }

        if ($date->isAfter(CarbonImmutable::today())) {
            return new ComputedDay(AttendanceStatus::Upcoming);
        }

        $times = $resolved->schedule->times();
        $start = self::minute($times[0]);
        $offsets = ScheduleTimes::offsets($times);

        // shortcut: a shift past midnight ends on the next date's row; computed once a nurse's DTR shows how IT prints one.
        if ($start + end($offsets) >= ScheduleTimes::DAY) {
            return new ComputedDay(AttendanceStatus::NotComputed);
        }

        // Each half of the day: its expected in and out, as minutes of the day, and the DTR's taps for it.
        $halves = [];

        foreach (array_chunk($offsets, 2) as $index => [$in, $out]) {
            $halves[$index] = ['in' => $start + $in, 'out' => $start + $out];
        }

        $taps = count($halves) === 2
            ? [[$row?->am_arrival, $row?->am_departure], [$row?->pm_arrival, $row?->pm_departure]]
            : [[$row?->am_arrival ?? $row?->pm_arrival, $row?->pm_departure ?? $row?->am_departure]];

        $covered = $this->covered(count($halves), $date, $covers);

        if (count($covered) === count($halves)) {
            return new ComputedDay($covered[0]);
        }

        $halves = array_diff_key($halves, $covered);
        $note = implode(', ', array_map(
            fn (int $half, AttendanceStatus $status): string => $status->getLabel().($half === 0 ? ' (morning)' : ' (afternoon)'),
            array_keys($covered),
            $covered,
        )) ?: null;

        $halves = $this->cutBySuspension($halves, $suspendedAt);

        if ($halves === []) {
            return new ComputedDay(AttendanceStatus::Suspended);
        }

        $scheduledHalves = count($taps);
        $tapped = array_filter(
            $halves,
            fn (array $half, int $index): bool => $taps[$index][0] !== null || $taps[$index][1] !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($tapped === []) {
            return new ComputedDay(AttendanceStatus::Absent, absent: count($halves) / $scheduledHalves, note: $note);
        }

        [$late, $lateCount, $undertime, $undertimeCount, $missing] = [0, 0, 0, 0, []];

        foreach ($halves as $index => $half) {
            [$arrived, $left] = $taps[$index];
            [$arrivedName, $leftName] = self::TAPS[$scheduledHalves][$index];

            if ($arrived === null) {
                $missing[] = $arrivedName;
            } elseif (($minutes = self::minute($arrived) - $half['in']) > 0) {
                $late += $minutes;
                $lateCount++;
            }

            if ($left === null) {
                $missing[] = $leftName;
            } elseif (($minutes = $half['out'] - self::minute($left)) > 0) {
                $undertime += $minutes;
                $undertimeCount++;
            }
        }

        return new ComputedDay(AttendanceStatus::Present, $late, $lateCount, $undertime, $undertimeCount, missing: $missing, note: $note);
    }

    /**
     * What covers each half of the day: leave, an OB or a travel order. A
     * half-day entry covers its half; on a one-block day it covers the whole
     * block. The first entry found for a half names it.
     *
     * shortcut: a one-block day is not split at its midpoint for a half day, as the spec says; split it when such schedules are in use.
     *
     * @param  SupportCollection<int, array{0: LeaveApplication|DayException, 1: AttendanceStatus}>  $covers
     * @return array<int, AttendanceStatus> half => what covers it
     */
    private function covered(int $halfCount, CarbonImmutable $date, SupportCollection $covers): array
    {
        $covered = [];

        foreach ($covers as [$entry, $status]) {
            if ($entry->date_from->greaterThan($date) || $entry->date_to->lessThan($date)) {
                continue;
            }

            $halves = match (true) {
                $halfCount === 2 && $entry->coverage === LeaveCoverage::FirstHalf => [0],
                $halfCount === 2 && $entry->coverage === LeaveCoverage::SecondHalf => [1],
                default => range(0, $halfCount - 1),
            };

            $covered += array_fill_keys($halves, $status);
        }

        return $covered;
    }

    /**
     * A suspension at 3:00 PM ends the day then: a half that starts at or
     * after it is dropped, and one that runs past it ends there.
     *
     * @param  array<int, array{in: int, out: int}>  $halves
     * @return array<int, array{in: int, out: int}>
     */
    private function cutBySuspension(array $halves, ?int $suspendedAt): array
    {
        if ($suspendedAt === null) {
            return $halves;
        }

        $cut = [];

        foreach ($halves as $index => $half) {
            if ($half['in'] < $suspendedAt) {
                $cut[$index] = ['in' => $half['in'], 'out' => min($half['out'], $suspendedAt)];
            }
        }

        return $cut;
    }

    /** "08:14" or "08:14:00" as minutes since midnight. */
    private static function minute(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
