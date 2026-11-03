<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\DayException;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\Attendance\MonthlyTotals;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

/**
 * The figures on the staff dashboard (filament.dashboard), a method for each
 * card. month() computes every DTR of the month, so it is kept ten minutes.
 */
final class DashboardFigures
{
    /** The leave card's groups and the types in each; any other type is "Others". */
    private const array LEAVE_GROUPS = [
        'Vacation' => [LeaveType::Vacation, LeaveType::Forced],
        'Sick' => [LeaveType::Sick],
        'Special privilege' => [LeaveType::SpecialPrivilege],
        'Wellness' => [LeaveType::Wellness],
    ];

    public function __construct(private MonthlyTotals $monthlyTotals) {}

    /**
     * The month's DTRs added up across the staff who earn leave credits: the
     * minutes by weekday (1 is Monday), each employee's day-equivalent added,
     * the share of days at work begun on time, and the five offices with the
     * most staff still without a DTR.
     *
     * @return array{dtrs: int, staff: int, late: int, undertime: int, dayEquivalent: string, absent: float, byWeekday: array<int, array{late: int, undertime: int}>, onTimeRate: int|null, officesBehind: list<array{office: string, uploaded: int, staff: int}>}
     */
    public function month(CarbonImmutable $month): array
    {
        return Cache::remember("dashboard.month.{$month->format('Y-m')}", now()->addMinutes(10), fn (): array => $this->computeMonth($month));
    }

    public function pendingApplications(): int
    {
        return LeaveApplication::query()->where('status', LeaveApplicationStatus::Pending)->count();
    }

    /**
     * Who is away today, on leave (unless cancelled), an OB or a travel order,
     * surname first; someone with two of them is listed once.
     *
     * @return list<array{employee: Employee, reason: string}>
     */
    public function outToday(CarbonImmutable $today): array
    {
        $covering = fn (Builder $query): Builder => $query->whereDate('date_from', '<=', $today)->whereDate('date_to', '>=', $today);

        return LeaveApplication::query()
            ->tap($covering)
            ->where('status', '!=', LeaveApplicationStatus::Cancelled)
            ->with('employee')
            ->get()
            ->map(fn (LeaveApplication $leave): array => ['employee' => $leave->employee, 'reason' => $leave->leave_type->getLabel()])
            ->concat(DayException::query()
                ->tap($covering)
                ->with('employee')
                ->get()
                ->map(fn (DayException $exception): array => ['employee' => $exception->employee, 'reason' => $exception->type->getLabel()]))
            ->unique(fn (array $row): int => $row['employee']->id)
            ->sortBy(fn (array $row): string => "{$row['employee']->last_name} {$row['employee']->first_name}")
            ->values()
            ->all();
    }

    /**
     * The days of leave filed for the year, unless cancelled, by group in the
     * card's order; a group with none is left out.
     *
     * @return array{total: string, types: list<array{label: string, days: string, percent: int}>}
     */
    public function leaveByType(int $year): array
    {
        $byType = LeaveApplication::query()
            ->where('status', '!=', LeaveApplicationStatus::Cancelled)
            ->whereYear('date_from', $year)
            ->toBase()
            ->selectRaw('leave_type, sum(days) as total')
            ->groupBy('leave_type')
            ->pluck('total', 'leave_type');

        $groups = [];

        foreach ($byType as $code => $days) {
            $label = collect(self::LEAVE_GROUPS)->search(fn (array $types): bool => in_array(LeaveType::from($code), $types, true)) ?: 'Others';
            $groups[$label] = bcadd($groups[$label] ?? '0', (string) $days, 3);
        }

        $total = array_reduce($groups, fn (string $sum, string $days): string => bcadd($sum, $days, 3), '0.000');
        $types = [];

        foreach ([...array_keys(self::LEAVE_GROUPS), 'Others'] as $label) {
            if (bccomp($groups[$label] ?? '0', '0', 3) > 0) {
                $types[] = ['label' => $label, 'days' => $groups[$label], 'percent' => (int) round((float) $groups[$label] / (float) $total * 100)];
            }
        }

        return ['total' => $total, 'types' => $types];
    }

    /** @return EloquentCollection<int, LeaveApplication> newest first */
    public function recentApplications(int $limit = 6): EloquentCollection
    {
        return LeaveApplication::query()->with('employee')->latest()->latest('id')->limit($limit)->get();
    }

    /**
     * What HR should see to next: the opening balances while the system has
     * not yet taken over, then the rest of last month's DTRs, else nothing.
     *
     * @param  array{dtrs: int, staff: int}  $month  from month()
     * @return array{step: 'opening-balances'|'uploads'|'caught-up', done: int, of: int}
     */
    public function nextStep(CarbonImmutable $today, array $month): array
    {
        $start = LeavePosting::configuredStart();

        if ($start !== null && $today->lessThan($start)) {
            $staff = $this->earningStaff();
            $of = $staff->count();
            $done = $staff->whereHas('leaveLedgerEntries', fn (Builder $entries): Builder => $entries->where('kind', LedgerKind::Opening))->count();

            if ($done < $of) {
                return ['step' => 'opening-balances', 'done' => $done, 'of' => $of];
            }
        }

        return ['step' => $month['dtrs'] < $month['staff'] ? 'uploads' : 'caught-up', 'done' => $month['dtrs'], 'of' => $month['staff']];
    }

    /** @return Builder<Employee> the active staff who earn leave credits */
    private function earningStaff(): Builder
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return Employee::query()->whereIn('employment_status', $earning)->where('is_active', true);
    }

    /** @return array{dtrs: int, staff: int, late: int, undertime: int, dayEquivalent: string, absent: float, byWeekday: array<int, array{late: int, undertime: int}>, onTimeRate: int|null, officesBehind: list<array{office: string, uploaded: int, staff: int}>} */
    private function computeMonth(CarbonImmutable $month): array
    {
        $rows = $this->monthlyTotals->rows(null, $month);
        $byWeekday = array_fill_keys(range(1, 7), ['late' => 0, 'undertime' => 0]);
        $offices = [];
        [$dtrs, $late, $undertime, $absent, $present, $onTime, $dayEquivalent] = [0, 0, 0, 0.0, 0, 0, '0.000'];

        foreach ($rows as ['employee' => $employee, 'dtr' => $dtr]) {
            $office = $employee->office->name;
            $offices[$office] ??= ['office' => $office, 'uploaded' => 0, 'staff' => 0];
            $offices[$office]['staff']++;

            if ($dtr === null) {
                continue;
            }

            $offices[$office]['uploaded']++;
            $dtrs++;
            $late += $dtr->late;
            $undertime += $dtr->undertime;
            $absent += $dtr->absent;
            // Each employee's month is converted on its own, as it is deducted.
            $dayEquivalent = bcadd($dayEquivalent, $dtr->dayEquivalent, 3);

            foreach ($dtr->days as $date => $day) {
                $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;
                $byWeekday[$weekday]['late'] += $day->late;
                $byWeekday[$weekday]['undertime'] += $day->undertime;

                if ($day->status === AttendanceStatus::Present) {
                    $present++;
                    $onTime += $day->lateCount === 0 ? 1 : 0;
                }
            }
        }

        $behind = array_values(array_filter($offices, fn (array $office): bool => $office['uploaded'] < $office['staff']));
        usort($behind, fn (array $a, array $b): int => [$b['staff'] - $b['uploaded'], $a['office']] <=> [$a['staff'] - $a['uploaded'], $b['office']]);

        return [
            'dtrs' => $dtrs,
            'staff' => count($rows),
            'late' => $late,
            'undertime' => $undertime,
            'dayEquivalent' => $dayEquivalent,
            'absent' => $absent,
            'byWeekday' => $byWeekday,
            'onTimeRate' => $present > 0 ? (int) round($onTime / $present * 100) : null,
            'officesBehind' => array_slice($behind, 0, 5),
        ];
    }
}
