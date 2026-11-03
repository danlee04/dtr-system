<?php

namespace App\Filament\Resources\DayExceptions\Schemas;

use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use App\Models\DayException;
use App\Models\Employee;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DayExceptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Three across, so a short form fills the modal instead of leaving half of it blank.
            ->columns(3)
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->options(fn (?DayException $record): array => self::employeeOptions($record?->employee_id))
                    ->searchable()
                    ->required()
                    ->columnSpan(2),
                Select::make('type')
                    ->options(ExceptionType::class)
                    ->default(ExceptionType::OfficialBusiness->value)
                    ->required(),
                DatePicker::make('date_from')
                    ->label('From')
                    ->required()
                    ->rules([
                        fn (Get $get, ?DayException $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            if (blank($get('employee_id')) || blank($get('date_to'))) {
                                return;
                            }

                            $coverage = $get->enum('coverage', LeaveCoverage::class);
                            $overlaps = DayException::query()
                                ->where('employee_id', $get('employee_id'))
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->whereDate('date_from', '<=', $get('date_to'))
                                ->whereDate('date_to', '>=', $value)
                                ->get()
                                // A morning and an afternoon may share a date.
                                ->reject(fn (DayException $other): bool => $coverage !== LeaveCoverage::WholeDay
                                    && $other->coverage !== LeaveCoverage::WholeDay
                                    && $other->coverage !== $coverage)
                                ->isNotEmpty();

                            if ($overlaps) {
                                $fail('These dates overlap an OB or travel order already recorded for this employee.');
                            }
                        },
                    ]),
                DatePicker::make('date_to')
                    ->label('To')
                    ->required()
                    ->afterOrEqual('date_from')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($get->enum('coverage', LeaveCoverage::class) !== LeaveCoverage::WholeDay && $value !== $get('date_from')) {
                                $fail('A morning or an afternoon covers one date only.');
                            }
                        },
                    ]),
                Select::make('coverage')
                    ->label('Covers')
                    ->options(LeaveCoverage::class)
                    ->default(LeaveCoverage::WholeDay->value)
                    ->required(),
                TextInput::make('reference_no')
                    ->label('Reference no.')
                    ->maxLength(50)
                    ->helperText('The slip or travel order number, if it has one.'),
                TextInput::make('remarks')
                    ->maxLength(255)
                    ->columnSpan(2),
            ]);
    }

    /**
     * Active employees, and the one already on the entry, so editing an old
     * entry for someone since separated still works.
     *
     * @return array<int, string>
     */
    private static function employeeOptions(?int $current): array
    {
        return Employee::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $current))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->full_name} — {$employee->employee_number}"])
            ->all();
    }
}
