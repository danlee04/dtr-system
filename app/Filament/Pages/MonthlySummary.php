<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\DtrUpload;
use App\Models\Office;
use App\Services\Attendance\MonthlyTotals;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * A month on one screen: every employee's late, undertime and absences from
 * their uploaded DTRs, narrowed to one office if HR likes, and the same as an
 * Excel sheet for the report HR sends. MonthlyTotals does the counting.
 */
class MonthlySummary extends Page
{
    use WithPagination;

    /** Rows a page can hold, for the pagination's select. */
    private const array PAGE_SIZES = [10, 25, 50, 100];

    protected string $view = 'filament.pages.monthly-summary';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Monthly Summary';

    /** From the office filter, which sends a string; blank for all offices. */
    public int|string|null $officeId = null;

    /** "Y-m", from the month input. */
    public string $month = '';

    /** From the pagination's select. */
    public int $perPage = 25;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', DtrUpload::class) ?? false;
    }

    public function mount(): void
    {
        // IT prints a month's DTRs once the month is over.
        $this->month = now()->subMonthNoOverflow()->format('Y-m');
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
            Action::make('download')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->chosenMonth() !== null)
                ->action(function (MonthlyTotals $totals): StreamedResponse {
                    Gate::authorize('viewAny', DtrUpload::class);

                    $this->validate([
                        'officeId' => ['nullable', 'integer', 'exists:offices,id'],
                        'month' => ['required', 'date_format:Y-m'],
                    ]);

                    $office = $this->chosenOffice();
                    $month = $this->chosenMonth();

                    return response()->streamDownload(
                        fn () => $totals->writeXlsx($office, $month, 'php://output'),
                        'monthly-summary-'.$month->format('Y-m').($office !== null ? '-'.Str::slug($office->name) : '').'.xlsx',
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    );
                }),
        ];
    }

    /** The office in the filter, or null for all offices. */
    private function chosenOffice(): ?Office
    {
        return filled($this->officeId) ? Office::find($this->officeId) : null;
    }

    /** The first of the chosen month, or null while the month input is blank. */
    private function chosenMonth(): ?CarbonImmutable
    {
        return CarbonImmutable::hasFormat($this->month, 'Y-m') ? CarbonImmutable::createFromFormat('!Y-m', $this->month) : null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $month = $this->chosenMonth();
        $rows = $month !== null ? app(MonthlyTotals::class)->rows($this->chosenOffice(), $month) : [];
        // Every row is counted for the totals and the download; the page shows a slice.
        $perPage = in_array($this->perPage, self::PAGE_SIZES, true) ? $this->perPage : 25;
        $page = $this->getPage();

        return [
            'offices' => Office::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'monthName' => $month?->format('F Y'),
            'rows' => new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage), count($rows), $perPage, $page),
            'pageSizes' => self::PAGE_SIZES,
            'withDtr' => count(array_filter($rows, fn (array $row): bool => $row['dtr'] !== null)),
        ];
    }
}
