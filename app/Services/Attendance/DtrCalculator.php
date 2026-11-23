<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\DtrSlot;
use App\Enums\HolidayType;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Models\DayException;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\ManualTap;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Late and undertime for each date of an uploaded DTR, against the schedule
 * the app has for that date. Nothing is stored: the figures follow the
 * schedules, holidays and leave as they stand, so fixing a schedule fixes
 * the month without uploading it again.
 *
 * HR's rule for now: a day with any tap is a day at work. A missing tap is
 * listed for HR to look at and costs nothing, except a missing time in, which
 * is undertime for the first half until HR fills it (HR, 2026-11-20); late is
 * read from the arrivals there are, and undertime from the departures. A half of the day with no
 * tap at all, while the other half has one, is undertime for all its hours
 * (HR, 2026-11-18). A work day with no tap at all, and no leave filed, is
 * absent, and undertime for all its hours too, so the CSC conversion charges
 * it (HR, 2026-11-18); when HR marks the DTR final, that part comes off SL
 * first, then VL (HR, 2026-11-19).
 *
 * Flexitime, for an employee HR checked, on an 8–5 day: late only after 9:00,
 * and the time out due is 9 hours after the time in, counted from 7:00 to
 * 9:00 (8:00 when there is none), so 4:00 to 6:00. The break stays 12–1, and
 * an untapped half or an absent day keeps its 8–5 hours (HR, 2026-11-19). A
 * morning-only day, the afternoon on leave or an OB, is late after 8:00 as on
 * 8–5 (HR, 2026-11-23).
 *
 * The DTR's columns are the slots: A.M. Arrival and Departure for the first
 * half of the day, P.M. for the second. O.T. is not read. A time HR copied
 * in from the signed DTR fills a blank, or corrects a time the device got
 * wrong (HR, 2026-11-18).
 */
final class DtrCalculator
{
    /** What each tap is called, in the order of the day. */
    private const TAPS = [
        2 => [['time in', 'lunch out'], ['lunch in', 'time out']],
        1 => [['time in', 'time out']],
    ];

    /** The column HR fills for each tap in TAPS. A one-block day's taps are read from A.M. arrival and P.M. departure first. */
    private const SLOTS = [
        2 => [[DtrSlot::AmArrival, DtrSlot::AmDeparture], [DtrSlot::PmArrival, DtrSlot::PmDeparture]],
        1 => [[DtrSlot::AmArrival, DtrSlot::PmDeparture]],
    ];

    public function __construct(private ScheduleResolver $resolver) {}

    public function compute(DtrUpload $upload): ComputedDtr
    {
        $employee = $upload->employee;

        // Late and undertime come off VL, so only staff who earn leave credits are computed (HR, 2026-11-03).
        if (! $upload->isComputed()) {
            return new ComputedDtr([]);
        }

        $from = $upload->period_start->toImmutable();
        $to = $upload->period_end->toImmutable();
        $rows = $upload->days->keyBy(fn (DtrDay $day): string => $day->date->toDateString());

        // Leave, OBs and travel orders, each with what it makes of the days it covers.
        $covers = LeaveApplication::query()
            ->whereBelongsTo($employee)
            ->whereIn('status', LeaveApplicationStatus::standing())
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

        $filled = ManualTap::query()
            ->whereBelongsTo($employee)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->groupBy(fn (ManualTap $tap): string => $tap->date->toDateString());

        $days = [];

        foreach ($this->resolver->days($employee, $from, $to) as $date => $resolved) {
            $columns = self::columns($rows->get($date), $filled->get($date));
            $days[$date] = $this->day($employee, CarbonImmutable::parse($date), $resolved, $columns, $covers, $suspensions->get($date));
        }

        return new ComputedDtr($days);
    }

    /**
     * Each time column of the date: as HR filled or corrected it, or else as printed.
     *
     * @param  ?SupportCollection<int, ManualTap>  $filled
     * @return array<string, ?string> slot => "08:14" or "08:14:00"
     */
    private static function columns(?DtrDay $row, ?SupportCollection $filled): array
    {
        $columns = [];

        foreach (DtrSlot::cases() as $slot) {
            $columns[$slot->value] = $filled?->firstWhere('slot', $slot)?->time ?? $row?->{$slot->value};
        }

        return $columns;
    }

    /**
     * @param  array<string, ?string>  $columns  slot => time
     * @param  SupportCollection<int, array{0: LeaveApplication|DayException, 1: AttendanceStatus}>  $covers
     */
    private function day(Employee $employee, CarbonImmutable $date, ResolvedDay $resolved, array $columns, SupportCollection $covers, ?int $suspendedAt): ComputedDay
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
            ? [[$columns['am_arrival'], $columns['am_departure']], [$columns['pm_arrival'], $columns['pm_departure']]]
            : [[$columns['am_arrival'] ?? $columns['pm_arrival'], $columns['pm_departure'] ?? $columns['am_departure']]];

        $covered = $this->covered(count($halves), $date, $covers);
        $away = $this->awayTimes($halves, $covered);

        if (count($covered) === count($halves)) {
            return new ComputedDay($covered[0], awayTimes: $away);
        }

        // Flexitime: late only after 9:00, and out 9 hours after a time in counted from 7:00 to 9:00;
        // 8:00 when there is none, or the morning is on an OB or leave (HR, 2026-11-19).
        if (self::isFlexiDay($employee, $resolved->schedule)) {
            $arrived = isset($covered[0]) || $taps[0][0] === null ? 480 : self::minute($taps[0][0]);

            // A morning-only day, the afternoon on leave, an OB, a TO or a gate pass: late after 8:00, as on 8–5 (HR, 2026-11-23).
            if (! isset($covered[1])) {
                $halves[0]['lateAfter'] = 540;
            }

            $halves[1]['dueOut'] = min(540, max(420, $arrived)) + 540;
        }

        $halves = array_diff_key($halves, $covered);
        $note = implode(', ', array_map(
            fn (int $half, AttendanceStatus $status): string => $status->getLabel().($half === 0 ? ' (morning)' : ' (afternoon)'),
            array_keys($covered),
            $covered,
        )) ?: null;

        $halves = $this->cutBySuspension($halves, $suspendedAt);

        if ($halves === []) {
            return new ComputedDay(AttendanceStatus::Suspended, awayTimes: $away);
        }

        $scheduledHalves = count($taps);
        $scheduled = $this->scheduleTimes($halves, $scheduledHalves);
        $tapped = array_filter(array_keys($halves), fn (int $index): bool => $taps[$index][0] !== null || $taps[$index][1] !== null);

        if ($tapped === []) {
            $fillable = array_merge(...array_map(fn (int $index): array => self::SLOTS[$scheduledHalves][$index], array_keys($halves)));

            // Undertime for all the hours not covered, 8 on 8–5, 10 on the four-day week (HR, 2026-11-18); that part of the CSC days comes off SL first, then VL (HR, 2026-11-19).
            $minutes = array_sum(array_map(fn (array $half): int => $half['out'] - $half['in'], $halves));

            return new ComputedDay(AttendanceStatus::Absent, undertime: $minutes, undertimeCount: 1, absent: count($halves) / $scheduledHalves, note: $note, fillable: $fillable, awayTimes: $away, absentMinutes: $minutes, scheduleTimes: $scheduled);
        }

        [$late, $lateCount, $undertime, $undertimeCount, $missing, $fillable] = [0, 0, 0, 0, [], []];

        foreach ($halves as $index => $half) {
            [$arrived, $left] = $taps[$index];
            [$arrivedName, $leftName] = self::TAPS[$scheduledHalves][$index];
            [$arrivedSlot, $leftSlot] = self::SLOTS[$scheduledHalves][$index];

            // A half with no tap at all, while the other has one, is undertime for all its hours (HR, 2026-11-18);
            // so is the first half with no time in, until HR fills it: undertime, not late, since a missing tap is no proof of a late arrival (HR, 2026-11-20).
            if (($arrived === null && $left === null) || ($index === 0 && $arrived === null)) {
                $undertime += $half['out'] - $half['in'];
                $undertimeCount++;
            }

            if ($arrived === null) {
                $missing[] = $arrivedName;
                $fillable[] = $arrivedSlot;
            } elseif (($minutes = self::minute($arrived) - ($half['lateAfter'] ?? $half['in'])) > 0) {
                $late += $minutes;
                $lateCount++;
            }

            if ($left === null) {
                $missing[] = $leftName;
                $fillable[] = $leftSlot;
            } elseif (($minutes = ($half['dueOut'] ?? $half['out']) - self::minute($left)) > 0) {
                $undertime += $minutes;
                $undertimeCount++;
            }
        }

        return new ComputedDay(AttendanceStatus::Present, $late, $lateCount, $undertime, $undertimeCount, missing: $missing, note: $note, fillable: $fillable, awayTimes: $away, scheduleTimes: $scheduled);
    }

    /**
     * The schedule's time for each slot of the halves left to judge, the
     * departure being the time out due (on flexitime, or cut by a suspension).
     *
     * @param  array<int, array{in: int, out: int, dueOut?: int}>  $halves
     * @return array<string, string>
     */
    private function scheduleTimes(array $halves, int $scheduledHalves): array
    {
        $times = [];

        foreach ($halves as $index => $half) {
            [$inSlot, $outSlot] = self::SLOTS[$scheduledHalves][$index];
            $times[$inSlot->value] = self::time($half['in']);
            $times[$outSlot->value] = self::time($half['dueOut'] ?? $half['out']);
        }

        return $times;
    }

    /**
     * The schedule's times for each part of the day on an OB, travel order or
     * gate pass, which the DTR shows in its blanks, as the paper form does
     * (HR, 2026-11-19; a gate pass too, 2026-11-23). Leave gets none: the
     * employee was not at work.
     *
     * @param  array<int, array{in: int, out: int}>  $halves
     * @param  array<int, AttendanceStatus>  $covered
     * @return array<string, array{time: string, status: AttendanceStatus}>
     */
    private function awayTimes(array $halves, array $covered): array
    {
        $away = [];

        foreach ($covered as $index => $status) {
            if (! in_array($status, [AttendanceStatus::OfficialBusiness, AttendanceStatus::TravelOrder, AttendanceStatus::GatePass], true)) {
                continue;
            }

            [$inSlot, $outSlot] = self::SLOTS[count($halves)][$index];
            $away[$inSlot->value] = ['time' => self::time($halves[$index]['in']), 'status' => $status];
            $away[$outSlot->value] = ['time' => self::time($halves[$index]['out']), 'status' => $status];
        }

        return $away;
    }

    /** Minutes since midnight as "08:00". */
    private static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * What covers each half of the day: leave, an OB, a travel order or a gate pass. A
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
                // Flexitime's time out due ends at the suspension too.
                $cut[$index] = [
                    ...$half,
                    'out' => min($half['out'], $suspendedAt),
                    ...(isset($half['dueOut']) ? ['dueOut' => min($half['dueOut'], $suspendedAt)] : []),
                ];
            }
        }

        return $cut;
    }

    /**
     * Whether flexitime applies: HR checked the employee, and the day's
     * schedule is 8:00–12:00 and 1:00–5:00, the only one it is granted on.
     */
    public static function isFlexiDay(Employee $employee, Schedule $schedule): bool
    {
        $times = $schedule->times();

        return $employee->flexitime && self::minute($times[0]) === 480 && ScheduleTimes::offsets($times) === [0, 240, 300, 540];
    }

    /** "08:14" or "08:14:00" as minutes since midnight. */
    private static function minute(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
