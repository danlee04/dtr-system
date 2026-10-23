<?php

namespace App\Services\Leave;

use App\Enums\DayKind;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Turns an application's dates into days of leave. A tracked type is counted
 * against the schedule: each work day is worth its schedule's leave_days for
 * VL, FL and SL, and 1 for SPL and Wellness. The other types count calendar
 * days and need no schedule, since a 105-day maternity leave cannot wait for
 * three months of rosters.
 */
final class LeaveDayCounter
{
    /** The longest range an application may cover. */
    private const int MAX_DAYS = 366;

    public function __construct(private ScheduleResolver $resolver) {}

    /**
     * @return array<string, string> Y-m-d → days, every date of the range, rest days at 0
     *
     * @throws ValidationException keyed date_from, date_to or coverage
     */
    public function count(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage, CarbonImmutable $start): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['date_to' => 'The last day cannot be before the first.']);
        }

        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['date_to' => 'One application covers at most '.self::MAX_DAYS.' days.']);
        }

        if ($coverage !== LeaveCoverage::WholeDay && ! $from->equalTo($to)) {
            throw ValidationException::withMessages(['coverage' => 'Half a day is filed for one date only.']);
        }

        if ($from->lessThan($start)) {
            throw ValidationException::withMessages(['date_from' => "Leave before {$start->format('M j, Y')} belongs in the opening balance."]);
        }

        if (in_array($type, [LeaveType::SpecialPrivilege, LeaveType::Wellness], true) && ($from->year !== today()->year || $to->year !== today()->year)) {
            throw ValidationException::withMessages(['date_from' => "{$type->getLabel()} is filed for dates in ".today()->year.' only.']);
        }

        $days = $type->isTracked() || $type === LeaveType::Forced
            ? $this->scheduled($employee, $type, $from, $to)
            : $this->calendar($employee, $from, $to);

        if ($coverage !== LeaveCoverage::WholeDay) {
            $days = array_map(fn (string $value): string => bcdiv($value, '2', 3), $days);
        }

        if (bccomp(array_reduce($days, fn (string $total, string $value): string => bcadd($total, $value, 3), '0'), '0', 3) === 0) {
            throw ValidationException::withMessages(['date_from' => "These dates have no work days for {$employee->full_name}."]);
        }

        return $days;
    }

    /** @return array<string, string> */
    private function scheduled(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        foreach ($this->resolver->days($employee, $from, $to) as $date => $day) {
            $days[$date] = match ($day->kind) {
                DayKind::NotEmployed => $this->notEmployed($employee, $date),
                DayKind::NoSchedule => throw ValidationException::withMessages([
                    'date_from' => 'No schedule on '.CarbonImmutable::parse($date)->format('M j, Y').'. Set it on the employee or in the duty roster first.',
                ]),
                DayKind::WorkDay => in_array($type, [LeaveType::SpecialPrivilege, LeaveType::Wellness], true)
                    ? '1.000'
                    : bcadd($day->schedule->leave_days, '0', 3),
                default => '0.000',
            };
        }

        return $days;
    }

    /** @return array<string, string> */
    private function calendar(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            if ($employee->date_hired?->greaterThan($date) || $employee->date_separated?->lessThan($date)) {
                $this->notEmployed($employee, $date->toDateString());
            }

            $days[$date->toDateString()] = '1.000';
        }

        return $days;
    }

    private function notEmployed(Employee $employee, string $date): never
    {
        throw ValidationException::withMessages([
            'date_from' => "{$employee->full_name} was not employed on ".CarbonImmutable::parse($date)->format('M j, Y').'.',
        ]);
    }
}
