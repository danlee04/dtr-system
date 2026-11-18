<?php

namespace App\Filament\Resources\DtrUploads\Tables;

use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Services\Attendance\DtrImporter;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DtrUploadsTable
{
    /**
     * The months with an upload, latest first: "2026-10" => "October 2026".
     *
     * @return array<string, string>
     */
    public static function months(): array
    {
        return DtrUpload::query()
            ->orderByDesc('period_start')
            ->get(['period_start'])
            ->mapWithKeys(fn (DtrUpload $upload): array => [$upload->period_start->format('Y-m') => $upload->period_start->format('F Y')])
            ->all();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'uploadedBy', 'days'])->withCount('days'))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->placeholder('Needs an employee')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'employee',
                        fn (Builder $employee): Builder => $employee->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('employee_name')
                    ->label('Name on the DTR')
                    ->searchable(),
                TextColumn::make('period_start')
                    ->label('Month')
                    ->state(fn (DtrUpload $record): string => $record->period())
                    ->sortable(),
                TextColumn::make('days_count')
                    ->label('Days with entries')
                    ->alignEnd(),
                TextColumn::make('late_and_undertime')
                    ->label('Late + undertime')
                    ->state(fn (DtrUpload $record): ?string => $record->isComputed() ? ($record->computed()->late + $record->computed()->undertime).' min' : null)
                    ->description(fn (DtrUpload $record): ?string => $record->isComputed() ? "{$record->computed()->dayEquivalent} day" : null)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('absent')
                    ->label('Absent (days)')
                    ->state(fn (DtrUpload $record): int|float|null => $record->isComputed() ? $record->computed()->absent : null)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn (DtrUpload $record): ?string => $record->uploadedBy?->name)
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->filters([
                // The month being worked on, the latest uploaded, so it does not sink under older months.
                SelectFilter::make('month')
                    ->options(fn (): array => self::months())
                    ->default(fn (): ?string => array_key_first(self::months()))
                    // The value comes from the browser: anything but "2026-10" filters nothing.
                    ->query(fn (Builder $query, array $data): Builder => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($data['value'] ?? '')) !== 1
                        ? $query
                        : $query
                            ->whereDate('period_start', '>=', CarbonImmutable::parse("{$data['value']}-01"))
                            ->whereDate('period_start', '<=', CarbonImmutable::parse("{$data['value']}-01")->endOfMonth())),
                Filter::make('needs_employee')
                    ->label('Needs an employee')
                    ->query(fn (Builder $query): Builder => $query->whereNull('employee_id')),
            ])
            // Shown in the toolbar at the right (filament.dtr-uploads.toolbar-filters), applied as they change.
            ->filtersLayout(FiltersLayout::Hidden)
            ->deferFilters(false)
            // The toolbar already shows them; a row of the same filters under it would say it twice.
            ->hiddenFilterIndicators()
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (DtrUpload $record): string => DtrUploadInfolist::heading($record))
                    ->modalWidth(Width::SevenExtraLarge),
                Action::make('chooseEmployee')
                    ->label('Choose employee')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (DtrUpload $record): bool => $record->employee_id === null)
                    ->authorize('update')
                    ->modalHeading('Choose the employee')
                    ->modalDescription(fn (DtrUpload $record): string => "Whose DTR is this? The name printed on it: {$record->employee_name}")
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(fn (): array => Employee::query()->orderBy('last_name')->orderBy('first_name')->get()->pluck('full_name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn (DtrUpload $record, array $data, DtrImporter $importer) => $importer->assign($record, Employee::findOrFail($data['employee_id']))),
                DeleteAction::make(),
            ]);
    }
}
