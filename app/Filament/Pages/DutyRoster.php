<?php

namespace App\Filament\Pages;

use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Services\Attendance\Roster;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * One office's month as a grid: employees down the side, days across, a
 * schedule code in each cell. Roster does the reading and saving; this page
 * only holds what HR is typing.
 */
class DutyRoster extends Page
{
    protected string $view = 'filament.pages.duty-roster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Duty roster';

    /** From the office dropdown, which sends a string. */
    public int|string|null $officeId = null;

    /** "Y-m", from the month input. */
    public string $month = '';

    /** @var array<int, array<int, string>> employee id → day → typed code */
    public array $cells = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', RosterEntry::class) ?? false;
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    // shortcut: switching office or month drops unsaved cells; add a confirm if HR loses work this way.
    public function updatedOfficeId(): void
    {
        $this->loadCells();
    }

    public function updatedMonth(): void
    {
        $this->loadCells();
    }

    public function save(Roster $roster): void
    {
        Gate::authorize('create', RosterEntry::class);

        $this->validateSelection();

        $changes = $roster->save(
            Office::findOrFail($this->officeId),
            CarbonImmutable::createFromFormat('!Y-m', $this->month),
            $this->cells,
        );

        Notification::make()
            ->success()
            ->title($changes === 0 ? 'No changes to save' : "Roster saved: {$changes} ".str('change')->plural($changes))
            ->send();

        $this->loadCells();
    }

    /**
     * Emptied first, so a month that fails validation never shows the
     * previous month's cells under it.
     */
    private function loadCells(): void
    {
        $this->cells = [];
        $this->resetErrorBag();

        if (blank($this->officeId)) {
            return;
        }

        $this->validateSelection();

        $this->cells = app(Roster::class)->read(
            Office::findOrFail($this->officeId),
            CarbonImmutable::createFromFormat('!Y-m', $this->month),
        );
    }

    private function validateSelection(): void
    {
        $this->validate([
            'officeId' => ['required', 'integer', 'exists:offices,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $office = filled($this->officeId) ? Office::find($this->officeId) : null;
        $month = CarbonImmutable::hasFormat($this->month, 'Y-m')
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)
            : null;

        return [
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'schedules' => Schedule::query()->where('is_active', true)->orderBy('code')->get(),
            'employees' => $office !== null ? app(Roster::class)->employees($office) : collect(),
            'days' => $month !== null
                ? array_map(fn (int $day): CarbonImmutable => $month->setDay($day), range(1, $month->daysInMonth))
                : [],
        ];
    }
}
