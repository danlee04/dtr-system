<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Tables;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Models\LeaveApplication;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyLeaveApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->date('M j, Y'),
                TextColumn::make('leave_type')
                    ->label('Type'),
                TextColumn::make('dates')
                    ->state(fn (LeaveApplication $record): string => $record->dates()),
                TextColumn::make('days')
                    ->alignEnd(),
                TextColumn::make('days_without_pay')
                    ->label('Without pay')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveApplicationStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->extraModalFooterActions([
                        PrintLeaveApplicationAction::make(),
                        // Not "cancel": every modal already has a button by that name, its Close.
                        CancelLeaveApplicationAction::make('cancelLeave')->outlined()->cancelParentActions(),
                    ]),
                PrintLeaveApplicationAction::make(),
                CancelLeaveApplicationAction::make(),
            ])
            ->emptyStateHeading('No leave filed yet');
    }
}
