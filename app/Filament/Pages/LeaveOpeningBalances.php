<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Services\Leave\LeavePosting;
use App\Services\Leave\OpeningBalances;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * One office's opening leave balances as a grid: employees down the side,
 * VL, SL, SPL, Wellness and VL/FL already taken across. OpeningBalances does
 * the reading and saving; this page only holds what HR is typing.
 */
class LeaveOpeningBalances extends Page
{
    protected string $view = 'filament.pages.leave-opening-balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Opening Balances';

    /** From the office dropdown, which sends a string. */
    public int|string|null $officeId = null;

    /** @var array<int, array<string, string>> employee id → leave type code → typed days */
    public array $rows = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', LeaveLedgerEntry::class) ?? false;
    }

    // shortcut: switching office drops unsaved cells; add a confirm if HR loses work this way.
    public function updatedOfficeId(): void
    {
        $this->rows = [];
        $this->resetErrorBag();

        if (blank($this->officeId)) {
            return;
        }

        $this->validateOffice();

        $this->rows = app(OpeningBalances::class)->read(Office::findOrFail($this->officeId));
    }

    public function save(OpeningBalances $openings): void
    {
        Gate::authorize('create', LeaveLedgerEntry::class);

        $start = LeavePosting::configuredStart();

        if ($start === null) {
            Notification::make()->danger()->title('Set LEAVE_START_DATE in .env first.')->send();

            return;
        }

        $this->validateOffice();

        $office = Office::findOrFail($this->officeId);
        $changes = $openings->save($office, $start, $this->rows);

        Notification::make()
            ->success()
            ->title($changes === 0 ? 'No changes to save' : "Opening balances saved: {$changes} ".str('change')->plural($changes))
            ->send();

        $this->rows = $openings->read($office);
    }

    private function validateOffice(): void
    {
        $this->validate(['officeId' => ['required', 'integer', 'exists:offices,id']]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $office = filled($this->officeId) ? Office::find($this->officeId) : null;

        return [
            'asOf' => LeavePosting::configuredStart()?->subDay(),
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'employees' => $office !== null ? app(OpeningBalances::class)->employees($office) : collect(),
            'columns' => OpeningBalances::COLUMNS,
        ];
    }
}
