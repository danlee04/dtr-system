<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\ScheduleAssigner;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Puts the selected employees on one schedule from a date, through
 * ScheduleAssigner: everyone or, when anyone selected already has a schedule
 * in that period, nobody.
 */
class AssignScheduleBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'assignSchedule';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Assign schedule')
            ->icon(Heroicon::OutlinedClock)
            ->authorize('create', ScheduleAssignment::class)
            ->authorizeIndividualRecords('update')
            ->modalDescription('An open-ended schedule that started earlier ends the day before. If anyone selected already has a schedule in this period, nobody is assigned.')
            ->schema([
                Select::make('schedule_id')
                    ->label('Schedule')
                    ->options(fn (): array => Schedule::assignableOptions())
                    ->required(),
                DatePicker::make('effective_from')
                    ->label('From')
                    ->required(),
                DatePicker::make('effective_to')
                    ->label('To')
                    ->afterOrEqual('effective_from')
                    ->helperText('Leave blank while it is still in effect.'),
            ])
            ->action(function (Collection $records, array $data, ScheduleAssigner $assigner): void {
                $problems = $assigner->assignAll(
                    $records,
                    Schedule::findOrFail($data['schedule_id']),
                    CarbonImmutable::parse($data['effective_from']),
                    filled($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to']) : null,
                );

                if ($problems !== []) {
                    Notification::make()
                        ->danger()
                        ->title('Nobody was assigned')
                        ->body($this->summary($problems))
                        ->persistent()
                        ->send();

                    return;
                }

                $count = $records->count();

                Notification::make()
                    ->success()
                    ->title("Schedule assigned to {$count} ".str('employee')->plural($count))
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * The first five problems, as plain text: they carry employee names.
     *
     * @param  list<string>  $problems
     */
    private function summary(array $problems): string
    {
        $summary = implode(' ', array_slice($problems, 0, 5));

        if (count($problems) > 5) {
            $summary .= ' …and '.(count($problems) - 5).' more.';
        }

        return $summary;
    }
}
