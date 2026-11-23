<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Tables;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\EditLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Models\LeaveApplication;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
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
            // A click on the row opens the application too.
            ->recordAction('view')
            // Lined up from the left, so View stays in one place whether the row also has Edit or not.
            ->recordActionsAlignment(Alignment::Start->value)
            // View and Edit only on the row, as on HR's list: with Print and Cancel it ran past a
            // 1024px screen; those wait in the application's footer (2026-11-23).
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Leave Application')
                    ->extraModalFooterActions([
                        PrintLeaveApplicationAction::make(),
                        EditLeaveApplicationAction::make()->cancelParentActions(),
                        // Not "cancel": every modal already has a button by that name, its Close.
                        CancelLeaveApplicationAction::make('cancelLeave')->outlined()->cancelParentActions(),
                    ]),
                // Their own, while pending (HR, 2026-11-23).
                EditLeaveApplicationAction::make(),
            ])
            ->emptyStateHeading('No leave filed yet');
    }
}
