<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Models\Schedule;
use App\Services\Attendance\ScheduleTimes;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(10)
                    ->alphaDash()
                    ->notIn(['OFF'])
                    ->unique()
                    // Checked and saved in capitals: "reg" is REG.
                    ->mutateStateForValidationUsing(self::capitals(...))
                    ->mutateDehydratedStateUsing(self::capitals(...))
                    ->helperText('Short, because it is typed in the duty roster: REG, CWW, AM, PM, N. OFF is the roster\'s day off.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                CheckboxList::make('work_days')
                    ->options(Schedule::WEEKDAYS)
                    ->columns(7)
                    ->columnSpanFull()
                    ->helperText('For employees assigned this schedule. A schedule used only in the duty roster needs none.'),
                Section::make('Hours')
                    ->description('A time earlier than the one before it is the next day, so a night shift needs nothing extra.')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        self::time('block1_in', 'First in')
                            ->required()
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $times = array_values(array_filter(
                                        [$value, $get('block1_out'), $get('block2_in'), $get('block2_out')],
                                        filled(...),
                                    ));
                                    $offsets = ScheduleTimes::offsets($times);

                                    if (end($offsets) >= ScheduleTimes::DAY) {
                                        $fail('From the first in to the last out must be under 24 hours. A time earlier than the one before it counts as the next day.');
                                    }
                                },
                            ]),
                        self::time('block1_out', 'First out')
                            ->required(),
                        self::time('block2_in', 'Second in')
                            ->requiredWith('block2_out'),
                        self::time('block2_out', 'Second out')
                            ->requiredWith('block2_in'),
                    ]),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    /**
     * Whole minutes, saved the way MySQL's TIME column returns them, so an
     * unchanged time is not a change.
     */
    private static function time(string $name, string $label): TimePicker
    {
        return TimePicker::make($name)
            ->label($label)
            ->seconds(false)
            ->format('H:i:s');
    }

    private static function capitals(?string $state): ?string
    {
        return $state === null ? null : mb_strtoupper(trim($state));
    }
}
