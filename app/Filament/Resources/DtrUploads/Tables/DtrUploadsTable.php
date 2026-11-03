<?php

namespace App\Filament\Resources\DtrUploads\Tables;

use App\Models\DtrUpload;
use App\Models\Employee;
use App\Services\Attendance\DtrImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DtrUploadsTable
{
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
                Filter::make('needs_employee')
                    ->label('Needs an employee')
                    ->query(fn (Builder $query): Builder => $query->whereNull('employee_id')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Daily Time Record')
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
