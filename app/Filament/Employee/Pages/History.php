<?php

namespace App\Filament\Employee\Pages;

use App\Enums\LeaveType;
use App\Enums\NavigationGroup;
use App\Services\Leave\LeaveLedger;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where the signed-in employee's leave went: one tab per type with a
 * balance, every credit and deduction with the balance it left.
 */
class History extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.employee.pages.history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MyLeave;

    protected static ?int $navigationSort = 2;

    /** The tab, as a type's code. Anything but a type with a balance shows VL. */
    public string $type = 'VL';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCurrentEmployee() ?? false;
    }

    public function currentType(): LeaveType
    {
        $type = LeaveType::tryFrom($this->type);

        return $type?->isTracked() ? $type : LeaveType::Vacation;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(LeaveLedger::class)->history(auth()->user()->employee, $this->currentType()))
            ->columns([
                TextColumn::make('posted_on')
                    ->label('Date')
                    ->date('M j, Y'),
                TextColumn::make('entry'),
                TextColumn::make('days')
                    ->formatStateUsing(fn (string $state): string => bccomp($state, '0', 3) > 0 ? "+{$state}" : $state)
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->alignEnd(),
                TextColumn::make('remarks')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->emptyStateHeading('Nothing recorded yet')
            ->paginated(false);
    }
}
