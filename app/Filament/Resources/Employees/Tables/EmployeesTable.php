<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('Employee No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Employee $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('office.name')
                    ->sortable(),
                TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('biometric_id')
                    ->label('Biometric ID')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('office')
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employment_status')
                    ->label('Status')
                    ->options(EmploymentStatus::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('biometric_id')
                    ->label('Biometric ID')
                    ->nullable()
                    ->trueLabel('Has an ID')
                    ->falseLabel('No ID yet'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
