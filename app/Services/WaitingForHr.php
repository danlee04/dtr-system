<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LedgerKind;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What waits for HR, for the topbar's bell on every page (HR, 2026-11-23):
 * leave to approve, DTRs that need an employee or are not final yet, and
 * staff still at work with no schedule or, hired before the system took
 * over and earning leave, no opening balance.
 */
final class WaitingForHr
{
    /**
     * @return array{pendingLeave: int, unmatchedDtrs: int, dtrsNotFinal: int, withoutSchedule: int, withoutOpenings: int}
     */
    public function counts(CarbonImmutable $today): array
    {
        $start = LeavePosting::configuredStart();

        return [
            'pendingLeave' => LeaveApplication::query()->where('status', LeaveApplicationStatus::Pending)->count(),
            'unmatchedDtrs' => DtrUpload::query()->whereNull('employee_id')->count(),
            // Those that need an employee are counted above.
            'dtrsNotFinal' => DtrUpload::query()->whereNotNull('employee_id')->whereNull('finalized_at')->count(),
            'withoutSchedule' => self::withoutSchedule(Employee::query(), $today)->count(),
            'withoutOpenings' => self::atWork(Employee::query(), $today)
                ->whereIn('employment_status', array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits()))
                // Whoever joins once the system has taken over starts from zero and needs none.
                ->when($start, fn (Builder $query, CarbonImmutable $start): Builder => $query->where(fn (Builder $hired): Builder => $hired->whereNull('date_hired')->orWhereDate('date_hired', '<', $start)))
                ->whereDoesntHave('leaveLedgerEntries', fn (Builder $entries): Builder => $entries->where('kind', LedgerKind::Opening))
                ->count(),
        ];
    }

    /**
     * Staff still at work with no schedule today and, as a nurse may have, no
     * roster this month; the Employees list's "No schedule" filter too.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public static function withoutSchedule(Builder $query, CarbonImmutable $today): Builder
    {
        return self::atWork($query, $today)
            ->whereDoesntHave('scheduleAssignments', fn (Builder $assignments): Builder => $assignments
                ->whereDate('effective_from', '<=', $today)
                ->where(fn (Builder $open): Builder => $open->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today)))
            ->whereDoesntHave('rosterEntries', fn (Builder $roster): Builder => $roster->whereBetween('date', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()]));
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    private static function atWork(Builder $query, CarbonImmutable $today): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $left): Builder => $left->whereNull('date_separated')->orWhereDate('date_separated', '>=', $today));
    }
}
