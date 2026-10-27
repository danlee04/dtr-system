<?php

namespace App\Filament\Resources\Holidays\Schemas;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Three across, so a short form fills the modal instead of leaving half of it blank.
            ->columns(3)
            ->components([
                DatePicker::make('date')
                    ->required()
                    // Not unique(): it compares the plain date string, which
                    // misses a date stored as "Y-m-d 00:00:00" (SQLite).
                    ->rules([
                        fn (?Holiday $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $taken = Holiday::query()
                                ->whereDate('date', $value)
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail('This date already has an entry.');
                            }
                        },
                    ]),
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                Select::make('type')
                    ->options(HolidayType::class)
                    ->required()
                    ->live()
                    ->helperText('Enter non-working days only. A special working day is an ordinary work day.'),
                TimePicker::make('suspension_starts_at')
                    ->label('Suspended from')
                    ->seconds(false)
                    ->format('H:i:s')
                    ->visible(fn (Get $get): bool => $get->enum('type', HolidayType::class) === HolidayType::Suspension)
                    ->helperText('Leave blank for the whole day. Work before this time is still expected.'),
            ]);
    }
}
