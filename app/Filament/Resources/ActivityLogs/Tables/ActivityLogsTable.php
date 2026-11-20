<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->wrap(false),
                TextColumn::make('causer.name')
                    ->label('Who')
                    // No one signed in: a console command, or the scheduler.
                    ->placeholder('System'),
                // The record's kind and id: the record itself may since have been deleted.
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state): string => self::kind($state))
                    ->description(fn (Activity $record): ?string => $record->subject_id !== null ? "#{$record->subject_id}" : null),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Activity $record): string => Str::headline($state ?? $record->description))
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('fields')
                    ->label('Fields')
                    ->state(fn (Activity $record): string => implode(', ', array_map(self::label(...), array_keys(self::changes($record)))))
                    ->placeholder('—')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('subject_type')
                    ->label('Record')
                    ->options(fn (): array => Activity::query()->distinct()->whereNotNull('subject_type')->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => self::kind($type)])->sort()->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('created_at', '<=', $until))),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->authorize('view')
                    ->modalHeading(fn (Activity $record): string => self::kind($record->subject_type).($record->subject_id !== null ? " #{$record->subject_id}" : ''))
                    ->modalDescription(fn (Activity $record): string => Str::headline($record->event ?? $record->description).' by '.($record->causer?->name ?? 'System').', '.$record->created_at->format('M j, Y g:i A'))
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(fn (Activity $record): View => view('filament.activity-log.changes', ['changes' => self::changes($record)]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    /** "App\Models\LeaveLedgerEntry" to "Leave ledger entry". */
    private static function kind(?string $type): string
    {
        return $type === null ? '—' : self::label(class_basename($type));
    }

    /** "last_name" or "LeaveLedgerEntry" in sentence case, as the app writes labels: "Last name". */
    public static function label(string $name): string
    {
        return Str::ucfirst(Str::lower(Str::headline($name)));
    }

    /**
     * Each field that changed, with its value before and after; a new record
     * has no before, a deleted one no after.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function changes(Activity $activity): array
    {
        $old = $activity->attribute_changes?->get('old') ?? [];
        $new = $activity->attribute_changes?->get('attributes') ?? [];
        $changes = [];

        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            $changes[$field] = ['old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
        }

        return $changes;
    }
}
