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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Where the signed-in employee's leave went: every credit and deduction of
 * every type in one table, each with the balance it left its type at, and a
 * filter to see one type the way its leave card reads.
 */
class History extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.employee.pages.history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MyLeave;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCurrentEmployee() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): array => app(LeaveLedger::class)->history(
                auth()->user()->employee,
                LeaveType::tryFrom((string) ($filters['leave_type']['value'] ?? '')),
            ))
            ->columns([
                TextColumn::make('posted_on')
                    ->label('Date')
                    ->date('M j, Y'),
                TextColumn::make('leave_type')
                    ->label('Leave type'),
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
            ->filters([
                SelectFilter::make('leave_type')
                    ->label('Leave type')
                    ->options(collect(LeaveType::tracked())->mapWithKeys(fn (LeaveType $type): array => [$type->value => $type->getLabel()])->all()),
            ])
            ->emptyStateHeading('Nothing recorded yet')
            ->paginated(false);
    }
}
