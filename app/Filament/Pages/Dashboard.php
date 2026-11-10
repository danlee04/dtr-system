<?php

namespace App\Filament\Pages;

use App\Enums\LeaveApplicationStatus;
use App\Enums\NavigationGroup;
use App\Models\DtrUpload;
use App\Models\LeaveApplication;
use App\Services\DashboardFigures;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Staff see the office at a glance (filament.dashboard), laid out after HR's
 * reference; DashboardFigures works out each card. Everyone else sees their
 * own leave and DTR in the same cards (filament.employee.dashboard).
 */
class Dashboard extends BaseDashboard
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Overview;

    public function content(Schema $schema): Schema
    {
        if (auth()->user()?->isStaff() ?? false) {
            return $schema->components([
                View::make('filament.dashboard')->viewData(fn (DashboardFigures $figures): array => $this->cards($figures)),
            ]);
        }

        return $schema->components([
            View::make('filament.employee.dashboard')->viewData(fn (LeaveLedger $ledger): array => $this->myCards($ledger)),
        ]);
    }

    /** @return array<string, mixed> */
    private function cards(DashboardFigures $figures): array
    {
        $today = CarbonImmutable::today();
        // The month whose DTRs IT has printed.
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();
        $month = $figures->month($lastMonth);

        return [
            'lastMonth' => $lastMonth,
            'month' => $month,
            'pending' => $figures->pendingApplications(),
            'outToday' => $figures->outToday($today),
            'leave' => $figures->leaveByType($today->year),
            'recent' => $figures->recentApplications(),
            'nextStep' => $figures->nextStep($today, $month),
            'takeover' => LeavePosting::configuredStart(),
        ];
    }

    /**
     * The signed-in employee's own: balances, their latest DTR as computed,
     * and their leave still waiting for HR.
     *
     * @return array<string, mixed>
     */
    private function myCards(LeaveLedger $ledger): array
    {
        $user = auth()->user();

        if (! $user->hasCurrentEmployee()) {
            return ['employee' => null];
        }

        $employee = $user->employee;
        $dtr = DtrUpload::query()->whereBelongsTo($employee)->with(['employee', 'days'])->latest('period_start')->first();

        return [
            'employee' => $employee,
            'balances' => $ledger->balances($employee),
            'dtr' => $dtr,
            'computed' => $dtr?->isComputed() ? $dtr->computed() : null,
            'pending' => LeaveApplication::query()
                ->whereBelongsTo($employee)
                ->where('status', LeaveApplicationStatus::Pending)
                ->orderBy('date_from')
                ->get(),
        ];
    }
}
