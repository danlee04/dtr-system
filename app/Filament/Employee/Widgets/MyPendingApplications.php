<?php

namespace App\Filament\Employee\Widgets;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Models\LeaveApplication;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The signed-in employee's leave still waiting for HR's approval. */
class MyPendingApplications extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Waiting for approval')
            ->query(fn (): Builder => LeaveApplication::query()
                ->where('employee_id', auth()->user()->employee_id)
                ->where('status', LeaveApplicationStatus::Pending)
                ->latest('date_from'))
            ->columns([
                TextColumn::make('leave_type')
                    ->label('Type'),
                TextColumn::make('dates')
                    ->state(fn (LeaveApplication $record): string => $record->dates()),
                TextColumn::make('days')
                    ->alignEnd(),
                TextColumn::make('days_without_pay')
                    ->label('Without pay')
                    ->alignEnd(),
            ])
            ->recordActions([
                PrintLeaveApplicationAction::make(),
            ])
            ->emptyStateHeading('Nothing waiting for approval')
            ->paginated(false);
    }
}
