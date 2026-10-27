<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The employee page's Details tab: the edit form's fields and groups, read
 * as labels and values, on one card like the edit modal.
 */
class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Employee')
                            ->contained(false)
                            ->columns(4)
                            ->schema([
                                TextEntry::make('employee_number'),
                                TextEntry::make('office.name')
                                    ->label('Office'),
                                TextEntry::make('employment_status'),
                                TextEntry::make('position')
                                    ->placeholder('—'),
                                TextEntry::make('last_name'),
                                TextEntry::make('first_name'),
                                TextEntry::make('middle_name')
                                    ->placeholder('—'),
                                TextEntry::make('suffix')
                                    ->placeholder('—'),
                            ]),
                        Section::make('Attendance')
                            ->contained(false)
                            ->columns(4)
                            ->schema([
                                TextEntry::make('biometric_id')
                                    ->label('Biometric ID')
                                    ->placeholder('—'),
                                TextEntry::make('date_hired')
                                    ->date('M j, Y')
                                    ->placeholder('—'),
                                TextEntry::make('date_separated')
                                    ->date('M j, Y')
                                    ->placeholder('—'),
                                IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                            ]),
                    ]),
            ]);
    }
}
