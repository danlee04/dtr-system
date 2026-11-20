<?php

namespace App\Filament\Resources\DtrUploads\Tables;

use App\Filament\Resources\DtrUploads\Actions\FinalizeDtrAction;
use App\Filament\Resources\DtrUploads\Actions\ReopenDtrAction;
use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Services\Attendance\DtrImporter;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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
                    // The printed name says whose it may be, under "Needs an employee"; with an employee it would only repeat the name.
                    ->description(fn (DtrUpload $record): ?string => $record->employee_id === null ? $record->employee_name : null)
                    ->wrap(false)
                    // The printed name is searched too, though it has no column of its own (HR, 2026-11-20).
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->whereHas('employee', fn (Builder $employee): Builder => $employee->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"))
                        ->orWhere('employee_name', 'like', "%{$search}%"))),
                // The figures together, right-aligned.
                TextColumn::make('late_and_undertime')
                    ->label('Late + undertime')
                    // In hours, as everywhere else (HR, 2026-11-20).
                    ->state(fn (DtrUpload $record): ?string => $record->isComputed() ? DtrUploadInfolist::hours($record->computed()->late + $record->computed()->undertime) : null)
                    ->description(fn (DtrUpload $record): ?string => $record->isComputed() ? "{$record->computed()->dayEquivalent} day" : null)
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('absent')
                    ->label('Absent (days)')
                    ->state(fn (DtrUpload $record): int|float|null => $record->isComputed() ? $record->computed()->absent : null)
                    ->placeholder('—')
                    ->alignEnd(),
                // Back by HR's request (2026-11-20), after it was dropped the same day; narrower, the row scrolls sideways or squeezes Uploaded.
                TextColumn::make('days_count')
                    ->label('Days with entries')
                    ->alignEnd()
                    ->visibleFrom('2xl'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn (DtrUpload $record): ?string => $record->uploadedBy?->name)
                    ->sortable()
                    ->visibleFrom('xl'),
                // Whether HR has checked it and marked it final, last, beside the actions (HR, 2026-11-20).
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (DtrUpload $record): ?string => match (true) {
                        ! $record->isComputed() => null,
                        $record->isFinal() => 'Final',
                        default => 'Not final',
                    })
                    ->badge()
                    ->icon(fn (?string $state): Heroicon => $state === 'Final' ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
                    ->color(fn (?string $state): string => $state === 'Final' ? 'success' : 'gray')
                    ->placeholder('—'),
            ])
            // By last name A–Z (HR, 2026-11-18); a DTR with no employee yet by its printed name, which leads with the surname.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('coalesce((select last_name from employees where employees.id = dtr_uploads.employee_id), dtr_uploads.employee_name)')
                ->orderBy('employee_name')
                ->orderBy('id'))
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
                // The DTRs HR has still to check and mark final.
                Filter::make('not_final')
                    ->label('Not final yet')
                    ->query(fn (Builder $query): Builder => $query->whereNull('finalized_at')),
            ])
            // Shown in the toolbar at the right (filament.dtr-uploads.toolbar-filters), applied as they change.
            ->filtersLayout(FiltersLayout::Hidden)
            ->deferFilters(false)
            // The toolbar already shows them; a row of the same filters under it would say it twice.
            ->hiddenFilterIndicators()
            // A click on the row opens the DTR too.
            ->recordAction('view')
            // Lined up from the left, so View stays in one place whether a row also has Delete or not (a final DTR has none) (HR, 2026-11-20).
            ->recordActionsAlignment(Alignment::Start->value)
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (DtrUpload $record): string => DtrUploadInfolist::heading($record))
                    ->modalWidth(Width::SevenExtraLarge)
                    ->extraModalFooterActions([FinalizeDtrAction::make(), ReopenDtrAction::make()]),
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
                    ->action(function (DtrUpload $record, array $data, DtrImporter $importer, Action $action): void {
                        try {
                            $importer->assign($record, Employee::findOrFail($data['employee_id']));
                        } catch (ValidationException $exception) {
                            // The importer names the field; the modal's fields live under its own state path.
                            throw ValidationException::withMessages(
                                collect($exception->errors())
                                    ->mapWithKeys(fn (array $messages, string $key): array => ["mountedActions.{$action->getNestingIndex()}.data.{$key}" => $messages])
                                    ->all(),
                            );
                        }
                    }),
                DeleteAction::make(),
            ]);
    }
}
