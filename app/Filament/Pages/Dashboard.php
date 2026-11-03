<?php

namespace App\Filament\Pages;

use App\Services\DashboardFigures;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Staff see the office at a glance (filament.dashboard), laid out after HR's
 * reference; DashboardFigures works out each card. Employees keep their own
 * widgets.
 */
class Dashboard extends BaseDashboard
{
    public function content(Schema $schema): Schema
    {
        if (! (auth()->user()?->isStaff() ?? false)) {
            return parent::content($schema);
        }

        return $schema->components([
            View::make('filament.dashboard')->viewData(fn (DashboardFigures $figures): array => $this->cards($figures)),
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
}
