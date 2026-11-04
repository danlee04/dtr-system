<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\DtrUpload;
use App\Services\Attendance\HabitualTardiness as Tardiness;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * A half year's habitual tardiness, the basis for HR's memo: everyone late ten
 * or more times in a month, their lates month by month, and who meets the CSC
 * rule. The service of the same name applies the rule.
 */
class HabitualTardiness extends Page
{
    protected string $view = 'filament.pages.habitual-tardiness';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Habitual Tardiness';

    /** From the year filter. */
    public int $year;

    /** From the half filter: 1 is January to June, 2 July to December. */
    public int $half;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', DtrUpload::class) ?? false;
    }

    public function mount(): void
    {
        // Last month's half year, as on the summary: IT prints a month's DTRs once the month is over.
        $lastMonth = now()->subMonthNoOverflow();
        $this->year = $lastMonth->year;
        $this->half = $lastMonth->month <= 6 ? 1 : 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (Tardiness $tardiness): StreamedResponse {
                    Gate::authorize('viewAny', DtrUpload::class);

                    $this->validate([
                        'year' => ['required', 'integer', 'between:2000,2100'],
                        'half' => ['required', 'integer', 'in:1,2'],
                    ]);

                    return response()->streamDownload(
                        fn () => $tardiness->writeXlsx($this->year, $this->half, 'php://output'),
                        "habitual-tardiness-{$this->year}-".($this->half === 1 ? 'jan-jun' : 'jul-dec').'.xlsx',
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    );
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        // A year or half sent from outside the filters lists nothing rather than fail.
        $valid = $this->year >= 2000 && $this->year <= 2100 && in_array($this->half, [1, 2], true);
        $rows = $valid ? app(Tardiness::class)->semester($this->year, $this->half) : [];
        $firstDtr = DtrUpload::query()->min('period_start');
        $oldest = min($valid ? $this->year : now()->year, $firstDtr !== null ? CarbonImmutable::parse($firstDtr)->year : now()->year);

        return [
            // Newest first, back to the first DTR uploaded or the year chosen, whichever is older.
            'years' => range(now()->year, $oldest),
            'label' => $valid ? Tardiness::label($this->year, $this->half) : 'Habitual tardiness',
            'months' => $valid ? Tardiness::months($this->year, $this->half) : [],
            'rows' => $rows,
            'habitualCount' => count(array_filter($rows, fn (array $row): bool => $row['habitual'] !== [])),
        ];
    }
}
