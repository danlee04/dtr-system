<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Services\Attendance\Roster;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

/**
 * A month as a grid: every active employee down the side, a page at a time,
 * days across, a schedule code in each cell. The office filter only narrows
 * the rows; the whole month stays loaded, so nothing typed is lost to the
 * filter or a page turn, and Save writes every row. Roster does the reading
 * and saving; this page only holds what HR is typing.
 */
class DutyRoster extends Page
{
    use WithPagination;

    /** Rows a page can hold, for the pagination's select. */
    private const array PAGE_SIZES = [10, 25, 50, 100];

    protected string $view = 'filament.pages.duty-roster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Duty Roster';

    /** From the office filter, which sends a string; blank for all offices. */
    public int|string|null $officeId = null;

    /** "Y-m", from the month input. */
    public string $month = '';

    /** From the pagination's select. */
    public int $perPage = 25;

    /** @var array<int, array<int, string>> employee id → day → typed code */
    public array $cells = [];

    /** @var array<int, array<int, string>> the cells as last read, to tell what is unsaved */
    #[Locked]
    public array $saved = [];

    /** The month the cells belong to: $month differs only while a change is refused. */
    #[Locked]
    public string $loadedMonth = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', RosterEntry::class) ?? false;
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->loadCells();
    }

    public function updatedOfficeId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /** Another month replaces the grid, so it waits until what was typed is saved. */
    public function updatedMonth(): void
    {
        if ($this->unsavedCells() !== []) {
            $this->month = $this->loadedMonth;

            Notification::make()
                ->warning()
                ->title('Save the roster first')
                ->body('Changing the month now would drop the codes you have not saved.')
                ->send();

            return;
        }

        $this->loadCells();
        $this->resetPage();
    }

    public function save(Roster $roster): void
    {
        Gate::authorize('create', RosterEntry::class);

        $this->validateMonth();

        $changes = $roster->save(null, CarbonImmutable::createFromFormat('!Y-m', $this->month), $this->cells);

        Notification::make()
            ->success()
            ->title($changes === 0 ? 'No changes to save' : "Roster saved: {$changes} ".str('change')->plural($changes))
            ->send();

        $this->loadCells();
    }

    /**
     * The month for every active employee, read fresh. Emptied first, so a
     * month that fails validation never shows the previous month's cells.
     */
    private function loadCells(): void
    {
        $this->cells = $this->saved = [];
        $this->loadedMonth = '';
        $this->resetErrorBag();

        $this->validateMonth();

        $this->cells = $this->saved = app(Roster::class)->read(null, CarbonImmutable::createFromFormat('!Y-m', $this->month));
        $this->loadedMonth = $this->month;
    }

    private function validateMonth(): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);
    }

    /**
     * The cells whose code differs from what is saved, as "employee id.day".
     * Worked out here, because a cell typed on another page or behind the
     * filter is off the screen, where the browser no longer marks it.
     *
     * @return array<string, true>
     */
    private function unsavedCells(): array
    {
        $unsaved = [];

        foreach ($this->cells as $employeeId => $days) {
            foreach (is_array($days) ? $days : [] as $day => $typed) {
                $code = is_scalar($typed) ? mb_strtoupper(trim((string) $typed)) : '';

                if ($code !== ($this->saved[$employeeId][$day] ?? '')) {
                    $unsaved["{$employeeId}.{$day}"] = true;
                }
            }
        }

        return $unsaved;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $month = $this->loadedMonth !== '' ? CarbonImmutable::createFromFormat('!Y-m', $this->loadedMonth) : null;
        $office = filled($this->officeId) ? Office::find($this->officeId) : null;
        $employees = $month !== null ? app(Roster::class)->employees($office) : collect();
        $perPage = in_array($this->perPage, self::PAGE_SIZES, true) ? $this->perPage : 25;
        $page = $this->getPage();

        return [
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'schedules' => Schedule::query()->where('is_active', true)->orderBy('code')->get(),
            'employees' => new LengthAwarePaginator($employees->forPage($page, $perPage)->values(), $employees->count(), $perPage, $page),
            'pageSizes' => self::PAGE_SIZES,
            'unsaved' => $this->unsavedCells(),
            'days' => $month !== null
                ? array_map(fn (int $day): CarbonImmutable => $month->setDay($day), range(1, $month->daysInMonth))
                : [],
        ];
    }
}
