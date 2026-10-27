<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmploymentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Headings, not cards: a card inside the modal is a box in a box.
                // Four across, the same as the employee's page, so nothing sits alone.
                Section::make('Employment')
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('employee_number')
                            ->required()
                            ->maxLength(30)
                            ->unique(),
                        Select::make('office_id')
                            ->label('Office')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('employment_status')
                            ->options(EmploymentStatus::class)
                            ->required(),
                        TextInput::make('position')
                            ->helperText('As on the leave form, e.g. Nurse II.')
                            ->maxLength(100),
                    ]),
                Section::make('Name')
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('middle_name')
                            ->maxLength(100),
                        TextInput::make('suffix')
                            ->maxLength(10),
                    ]),
                Section::make('Attendance')
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('biometric_id')
                            ->label('Biometric ID')
                            ->helperText('Its ID on the biometric device. Blank until known.')
                            ->maxLength(20)
                            ->alphaNum()
                            ->unique(),
                        DatePicker::make('date_hired'),
                        DatePicker::make('date_separated')
                            ->afterOrEqual('date_hired'),
                        // Label on top, so the switch lines up with the fields beside it.
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
