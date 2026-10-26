<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                // An admin cannot demote or deactivate themselves: the last
                // admin doing so would leave nobody able to undo it.
                // Employee accounts are made from the Employees screen.
                Select::make('role')
                    ->options([
                        UserRole::Admin->value => UserRole::Admin->getLabel(),
                        UserRole::Hr->value => UserRole::Hr->getLabel(),
                    ])
                    ->default(UserRole::Hr->value)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                // The current link is offered even when that employee has left,
                // so the account can still be saved, say to deactivate it.
                Select::make('employee_id')
                    ->label('Linked employee')
                    ->helperText('So this account can also open its own leave.')
                    ->options(fn (?User $record): array => Employee::withTrashed()
                        ->where(fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at'))
                        ->orWhere('id', $record?->employee_id)
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->full_name} — {$employee->employee_number}"])
                        ->all())
                    ->searchable()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : null),
            ]);
    }
}
