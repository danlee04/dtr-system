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
                Section::make('Employee')
                    ->columns(2)
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
                        Select::make('employment_status')
                            ->options(EmploymentStatus::class)
                            ->required(),
                    ]),
                Section::make('Attendance')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('biometric_id')
                            ->label('Biometric ID')
                            ->helperText('The ID this person uses on the biometric device. Leave it blank until it is known.')
                            ->maxLength(20)
                            ->alphaNum()
                            ->unique(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        DatePicker::make('date_hired'),
                        DatePicker::make('date_separated')
                            ->afterOrEqual('date_hired'),
                    ]),
            ]);
    }
}
