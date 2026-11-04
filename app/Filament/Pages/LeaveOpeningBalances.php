<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Services\Leave\LeavePosting;
use App\Services\Leave\OpeningBalances;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

/**
 * The opening leave balances as a grid: everyone who earns credits down the
 * side, a page at a time, VL, SL, SPL, Wellness and VL/FL already taken
 * across. The office filter only narrows the rows; every row stays loaded, so
 * nothing typed is lost to the filter or a page turn, and Save writes them
 * all. OpeningBalances does the reading and saving; this page only holds what
 * HR is typing.
 */
class LeaveOpeningBalances extends Page
{
    use WithPagination;

    /** Rows a page can hold, for the pagination's select. */
    private const array PAGE_SIZES = [10, 25, 50, 100];

    protected string $view = 'filament.pages.leave-opening-balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Opening Balances';

    /** From the office filter, which sends a string; blank for all offices. */
    public int|string|null $officeId = null;

    /** From the pagination's select. */
    public int $perPage = 25;

    /** @var array<int, array<string, string>> employee id → leave type code → typed days */
    public array $rows = [];

    /** @var array<int, array<string, string>> the rows as last read, to tell what is unsaved */
    #[Locked]
    public array $saved = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', LeaveLedgerEntry::class) ?? false;
    }

    public function mount(): void
    {
        $this->loadRows();
    }

    public function updatedOfficeId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function save(OpeningBalances $openings): void
    {
        Gate::authorize('create', LeaveLedgerEntry::class);

        $start = LeavePosting::configuredStart();

        if ($start === null) {
            Notification::make()->danger()->title('Set LEAVE_START_DATE in .env first.')->send();

            return;
        }

        $changes = $openings->save(null, $start, $this->rows);

        Notification::make()
            ->success()
            ->title($changes === 0 ? 'No changes to save' : "Opening balances saved: {$changes} ".str('change')->plural($changes))
            ->send();

        $this->loadRows();
    }

    private function loadRows(): void
    {
        $this->resetErrorBag();
        $this->rows = $this->saved = app(OpeningBalances::class)->read(null);
    }

    /**
     * The cells whose days differ from what is saved, as "employee id.code";
     * "7.5" and "7.500" are the same. Worked out here, because a cell typed on
     * another page or behind the filter is off the screen, where the browser
     * no longer marks it.
     *
     * @return array<string, true>
     */
    private function unsavedCells(): array
    {
        $unsaved = [];

        foreach ($this->rows as $employeeId => $cells) {
            foreach (is_array($cells) ? $cells : [] as $code => $value) {
                $typed = is_scalar($value) ? trim((string) $value) : '';
                $saved = $this->saved[$employeeId][$code] ?? '';
                $same = $saved !== '' && preg_match('/^\d+(\.\d+)?\z/', $typed) === 1
                    ? bccomp($typed, $saved, 3) === 0
                    : $typed === $saved;

                if (! $same) {
                    $unsaved["{$employeeId}.{$code}"] = true;
                }
            }
        }

        return $unsaved;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $office = filled($this->officeId) ? Office::find($this->officeId) : null;
        $employees = app(OpeningBalances::class)->employees($office);
        $perPage = in_array($this->perPage, self::PAGE_SIZES, true) ? $this->perPage : 25;
        $page = $this->getPage();
        $onPage = $employees->forPage($page, $perPage)->values();

        return [
            'asOf' => LeavePosting::configuredStart()?->subDay(),
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'employees' => new LengthAwarePaginator($onPage, $employees->count(), $perPage, $page),
            'pageSizes' => self::PAGE_SIZES,
            'columns' => OpeningBalances::COLUMNS,
            'limits' => OpeningBalances::LIMITS,
            'locked' => app(OpeningBalances::class)->locked($onPage->modelKeys()),
            'unsaved' => $this->unsavedCells(),
            // Counted from what is typed, saved or not, among the rows the filter shows.
            'entered' => $employees->filter(fn (Employee $employee): bool => array_filter($this->rows[$employee->id] ?? [], filled(...)) !== [])->count(),
        ];
    }
}
