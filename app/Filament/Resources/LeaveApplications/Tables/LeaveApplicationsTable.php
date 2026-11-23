<?php

namespace App\Filament\Resources\LeaveApplications\Tables;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Filament\Resources\LeaveApplications\Actions\ApproveLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\DisapproveLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\EditLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Models\LeaveApplication;
use App\Models\Office;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
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
                    ->sortable()
                    // Filed and without pay step aside on a laptop or tablet, so the table fits.
                    ->visibleFrom('xl'),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->wrap(false)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'employee',
                        fn (Builder $employee): Builder => $employee->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('leave_type')
                    ->label('Type')
                    ->formatStateUsing(fn (LeaveType $state): string => $state->value),
                TextColumn::make('dates')
                    ->label('Dates')
                    ->state(fn (LeaveApplication $record): string => $record->dates())
                    ->wrap(false),
                TextColumn::make('days')
                    ->label('Days')
                    ->alignEnd(),
                TextColumn::make('days_without_pay')
                    ->label('Without pay')
                    ->alignEnd()
                    ->visibleFrom('xl'),
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
            // A click on the row opens the application too.
            ->recordAction('view')
            // Lined up from the left, so View stays in one place whether the row also has Edit or not.
            ->recordActionsAlignment(Alignment::Start->value)
            // View and Edit only on the row: with Print, Approve and Cancel it ran past a 1024px screen
            // with a long name; the rest wait in the application's footer (2026-11-23).
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Leave Application')
                    // Wide enough for the A4 form inside it.
                    ->modalWidth(Width::FiveExtraLarge)
                    ->extraModalFooterActions([
                        PrintLeaveApplicationAction::make(),
                        EditLeaveApplicationAction::make()->cancelParentActions(),
                        ApproveLeaveApplicationAction::make()->cancelParentActions(),
                        // Decided after reading the application, beside Approve (HR, 2026-11-23).
                        DisapproveLeaveApplicationAction::make()->outlined()->cancelParentActions(),
                        // Not "cancel": every modal already has a button by that name, its Close.
                        CancelLeaveApplicationAction::make('cancelLeave')->outlined()->cancelParentActions(),
                    ]),
                EditLeaveApplicationAction::make(),
            ]);
    }
}
