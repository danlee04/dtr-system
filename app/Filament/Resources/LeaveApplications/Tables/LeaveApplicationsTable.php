<?php

namespace App\Filament\Resources\LeaveApplications\Tables;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Filament\Resources\LeaveApplications\Actions\ApproveLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Models\LeaveApplication;
use App\Models\Office;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('employee'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'employee',
                        fn (Builder $employee): Builder => $employee->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('leave_type')
                    ->label('Type')
                    ->formatStateUsing(fn (LeaveType $state): string => $state->value),
                TextColumn::make('dates')
                    ->label('Dates')
                    ->state(fn (LeaveApplication $record): string => $record->dates()),
                TextColumn::make('days')
                    ->label('Days')
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
                SelectFilter::make('leave_type')
                    ->label('Type')
                    ->options(LeaveType::class),
                SelectFilter::make('office')
                    ->options(fn (): array => Office::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('employee', fn (Builder $employee): Builder => $employee->where('office_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                ViewAction::make()
                    ->extraModalFooterActions([
                        PrintLeaveApplicationAction::make(),
                        ApproveLeaveApplicationAction::make()->cancelParentActions(),
                        // Not "cancel": every modal already has a button by that name, its Close.
                        CancelLeaveApplicationAction::make('cancelLeave')->outlined()->cancelParentActions(),
                    ]),
                PrintLeaveApplicationAction::make(),
                ApproveLeaveApplicationAction::make(),
                CancelLeaveApplicationAction::make(),
            ]);
    }
}
