<?php

namespace App\Filament\Pages;

use App\Enums\LeaveApplicationStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\DtrUploads\Tables\DtrUploadsTable;
use App\Models\DtrUpload;
use App\Models\LeaveApplication;
use App\Services\DashboardFigures;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Staff see the office at a glance (filament.dashboard), laid out after HR's
 * reference; DashboardFigures works out each card. Everyone else sees their
 * own leave and DTR in the same cards (filament.employee.dashboard).
 */
class Dashboard extends BaseDashboard
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Overview;

    /** Solid, as every icon in the navy sidebar (HR's reference, 2026-11-23). */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    /** "Y-m", from the month picker over the staff dashboard. */
    public string $month = '';

    public function mount(): void
    {
        $this->month = self::lastMonth()->format('Y-m');
    }

    /** IT prints a month's DTRs once it is over, so the dashboard opens on last month. */
    private static function lastMonth(): CarbonImmutable
    {
        return CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth();
    }

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
        // The month picked, or last month; the browser sends anything, so only a real "Y-m" counts.
        $lastMonth = CarbonImmutable::hasFormat($this->month, 'Y-m') && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)
            : self::lastMonth();
        $month = $figures->month($lastMonth);
        $finals = $figures->finals($lastMonth);

        return [
            'lastMonth' => $lastMonth,
            // For the month select: every month of this year (HR, 2026-11-24), and any earlier month with a DTR, newest first.
            'months' => collect(range(1, 12))
                ->mapWithKeys(fn (int $number): array => [$today->setDate($today->year, $number, 1)->format('Y-m') => $today->setDate($today->year, $number, 1)->format('F Y')])
                ->merge(DtrUploadsTable::months())
                ->sortKeysDesc()
                ->all(),
            'month' => $month,
            // Closing the month: the DTRs HR has marked final, and the offices not done (HR, 2026-11-20).
            'finalCount' => array_sum($finals),
            'officesNotDone' => $figures->officesNotDone($month, $finals),
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
