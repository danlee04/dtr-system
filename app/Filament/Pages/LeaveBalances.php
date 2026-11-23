<?php

namespace App\Filament\Pages;

use App\Enums\EmploymentStatus;
use App\Enums\NavigationGroup;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use UnitEnum;

/**
 * Every employee's leave balances at the end of a month, with what the month
 * took from each in red (HR, 2026-11-20). It takes Opening Balances' place in
 * the sidebar; the openings are still entered there, from a button here.
 */
class LeaveBalances extends Page
{
    use WithPagination;

    /** Rows a page can hold, for the pagination's select. */
    private const array PAGE_SIZES = [10, 25, 50, 100];

    protected string $view = 'filament.pages.leave-balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Scale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Balances';

    /** From the office filter, which sends a string; blank for all offices. */
    public int|string|null $officeId = null;

    /** "Y-m", from the month select. */
    public string $month = '';

    /** From the pagination's select. */
    public int $perPage = 25;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', LeaveLedgerEntry::class) ?? false;
    }

    public function mount(): void
    {
        $this->month = array_key_first($this->months()) ?? now()->format('Y-m');

        // Last month, as the other reports open on, when it is one of them.
        $lastMonth = now()->subMonthNoOverflow()->format('Y-m');

        if (array_key_exists($lastMonth, $this->months())) {
            $this->month = $lastMonth;
        }
    }

    /** A new office, month or page size starts again on the first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['officeId', 'month', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openingBalances')
                ->label('Opening balances')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(LeaveOpeningBalances::getUrl())
                ->visible(fn (): bool => LeaveOpeningBalances::canAccess()),
        ];
    }

    /**
     * The months from the leave start to this one, newest first; this month
     * alone while no start is set.
     *
     * @return array<string, string> "Y-m" → "October 2026"
     */
    public function months(): array
    {
        $start = LeavePosting::configuredStart() ?? CarbonImmutable::today()->startOfMonth();

        return collect(CarbonPeriod::create($start->startOfMonth(), '1 month', CarbonImmutable::today()->startOfMonth()))
            ->mapWithKeys(fn ($month): array => [$month->format('Y-m') => $month->format('F Y')])
            ->sortKeysDesc()
            ->all();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        // The browser sends anything; only a month in the list counts.
        $key = array_key_exists($this->month, $this->months()) ? $this->month : array_key_first($this->months());
        $month = CarbonImmutable::createFromFormat('!Y-m', $key);
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());
        $perPage = in_array($this->perPage, self::PAGE_SIZES, true) ? $this->perPage : 25;

        $employees = Employee::query()
            ->when(filled($this->officeId), fn (Builder $query): Builder => $query->where('office_id', $this->officeId))
            ->whereIn('employment_status', $earning)
            // Those separated in or after the month still had balances then.
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereDate('date_separated', '>=', $month->startOfMonth()->toDateString()))
            ->with('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);

        return [
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'monthName' => $month->format('F Y'),
            'employees' => $employees,
            'figures' => app(LeaveLedger::class)->monthly($employees->getCollection()->modelKeys(), $month),
            'pageSizes' => self::PAGE_SIZES,
        ];
    }
}
