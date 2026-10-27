<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\ScheduleAssigner;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The employee's fixed schedules over time. Saving goes through
 * ScheduleAssigner, so assignments never overlap.
 */
class ScheduleAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleAssignments';

    protected static ?string $title = 'Schedules';

    protected static ?string $modelLabel = 'schedule';

    /** It sits on the employee's view page, which Filament would make read-only. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('schedule_id')
                    ->label('Schedule')
                    ->options(fn (?ScheduleAssignment $record): array => Schedule::assignableOptions($record?->schedule_id))
                    ->required(),
                DatePicker::make('effective_from')
                    ->label('From')
                    ->required()
                    ->rules([
                        fn (Get $get, RelationManager $livewire, ?ScheduleAssignment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $livewire, $record): void {
                            $conflict = app(ScheduleAssigner::class)->conflict(
                                $livewire->getOwnerRecord(),
                                CarbonImmutable::parse($value),
                                filled($get('effective_to')) ? CarbonImmutable::parse($get('effective_to')) : null,
                                $record,
                            );

                            if ($conflict !== null) {
                                $fail("Overlaps {$conflict->describe()}.");
                            }
                        },
                    ]),
                DatePicker::make('effective_to')
                    ->label('To')
                    ->afterOrEqual('effective_from')
                    ->helperText('Leave blank while it is still in effect.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (ScheduleAssignment $record): string => $record->describe())
            ->columns([
                TextColumn::make('schedule.code')
                    ->label('Code'),
                TextColumn::make('schedule.name')
                    ->label('Schedule'),
                TextColumn::make('effective_from')
                    ->label('From')
                    ->date('M j, Y'),
                TextColumn::make('effective_to')
                    ->label('To')
                    ->date('M j, Y')
                    ->placeholder('No end date'),
            ])
            ->defaultSort('effective_from', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Assign schedule')
                    ->modalDescription('An open-ended schedule that started earlier ends the day before.')
                    ->using(fn (array $data, RelationManager $livewire, ScheduleAssigner $assigner): ScheduleAssignment => $assigner->assign(
                        $livewire->getOwnerRecord(),
                        Schedule::findOrFail($data['schedule_id']),
                        CarbonImmutable::parse($data['effective_from']),
                        filled($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to']) : null,
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
