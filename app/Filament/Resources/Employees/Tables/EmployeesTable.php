<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmploymentStatus;
use App\Filament\Resources\Employees\Actions\AssignScheduleBulkAction;
use App\Models\Employee;
use App\Services\WaitingForHr;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->wrap(false)
                    ->searchable(['last_name', 'first_name'])
                    // Not sortable(['last_name', 'first_name']): Filament 5.9 sorts by the array's last column first.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('last_name', $direction)
                        ->orderBy('first_name', $direction)),
                TextColumn::make('office.name')
                    ->sortable(),
                TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge(),
                // The least-used columns step aside on a laptop or tablet; the search still covers them.
                TextColumn::make('biometric_id')
                    ->label('Biometric ID')
                    ->placeholder('—')
                    ->searchable()
                    ->visibleFrom('2xl'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->visibleFrom('xl'),
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
                // Where the topbar bell's "Staff with no schedule" leads (HR, 2026-11-23).
                Filter::make('no_schedule')
                    ->label('No schedule')
                    ->query(fn (Builder $query): Builder => WaitingForHr::withoutSchedule($query, CarbonImmutable::today())),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // The same width as New employee.
                EditAction::make()
                    ->modalWidth(Width::SixExtraLarge),
            ])
            ->toolbarActions([
                AssignScheduleBulkAction::make(),
            ]);
    }
}
