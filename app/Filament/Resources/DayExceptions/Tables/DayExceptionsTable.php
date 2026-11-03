<?php

namespace App\Filament\Resources\DayExceptions\Tables;

use App\Enums\ExceptionType;
use App\Models\DayException;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DayExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('employee'))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'employee',
                        fn (Builder $employee): Builder => $employee->where('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('date_from')
                    ->label('Dates')
                    ->state(fn (DayException $record): string => $record->dates())
                    ->sortable(),
                TextColumn::make('reference_no')
                    ->label('Reference no.')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('remarks')
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->defaultSort('date_from', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(ExceptionType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
