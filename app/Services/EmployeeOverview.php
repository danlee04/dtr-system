<?php

namespace App\Services;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\MonthlyTotals;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an employee's Overview tab shows (filament.employees.cards and
 * needs-a-look): last month's lates, the leave balances and the schedule, and
 * the first thing about the employee that HR should look at.
 */
final class EmployeeOverview
{
    public function __construct(private MonthlyTotals $monthlyTotals, private LeaveLedger $leaveLedger) {}

    /**
     * @return array{earns: bool, month: CarbonImmutable, lastMonth: ComputedDtr|null, balances: array<string, string>, schedule: Schedule|null, needsALook: array{step: string, count?: int}|null}
     */
    public function for(Employee $employee, CarbonImmutable $today): array
    {
        $month = $today->subMonthNoOverflow()->startOfMonth();
        $earns = $employee->employment_status->earnsLeaveCredits();
        // Only the DTRs of those who earn leave are computed.
        $lastMonth = $earns ? $this->monthlyTotals->dtr($employee, $month) : null;
        $schedule = $employee->scheduleAssignments()
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->latest('effective_from')
            ->with('schedule')
            ->first()?->schedule;

        return [
            'earns' => $earns,
            'month' => $month,
            'lastMonth' => $lastMonth,
            'balances' => $earns ? $this->leaveLedger->balances($employee) : [],
            'schedule' => $schedule,
            'needsALook' => $this->needsALook($employee, $today, $schedule, $lastMonth),
        ];
    }

    /**
     * The first of these, for someone still at work: no schedule, no opening
     * balances, leave waiting for approval, ten or more lates last month.
     *
     * @return array{step: string, count?: int}|null
     */
    private function needsALook(Employee $employee, CarbonImmutable $today, ?Schedule $schedule, ?ComputedDtr $lastMonth): ?array
    {
        if (! $employee->is_active || $employee->trashed() || $employee->date_separated?->lt($today) === true) {
            return null;
        }

        // A nurse may have no fixed schedule but this month's roster.
        if ($schedule === null && ! $employee->rosterEntries()->whereBetween('date', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])->exists()) {
            return ['step' => 'schedule'];
        }

        $start = LeavePosting::configuredStart();

        // Whoever joins once the system has taken over starts from zero and needs none.
        if ($employee->employment_status->earnsLeaveCredits()
            && ($start === null || $employee->date_hired === null || $employee->date_hired->lt($start))
            && ! $employee->leaveLedgerEntries()->where('kind', LedgerKind::Opening)->exists()) {
            return ['step' => 'opening-balances'];
        }

        $waiting = $employee->leaveApplications()->where('status', LeaveApplicationStatus::Pending)->count();

        if ($waiting > 0) {
            return ['step' => 'waiting', 'count' => $waiting];
        }

        if ($lastMonth !== null && $lastMonth->lateCount >= MonthlyTotals::FREQUENT_LATES) {
            return ['step' => 'lates', 'count' => $lastMonth->lateCount];
        }

        return null;
    }
}
