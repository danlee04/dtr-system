<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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
                // admin doing so would leave nobody able to undo it. A new
                // account starts as the role with the least access.
                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::Employee->value)
                    ->required()
                    ->live()
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                Select::make('employee_id')
                    ->label('Linked employee')
                    ->helperText('Required for an Employee account. On an Admin or HR account, it also shows them their own leave.')
                    ->options(fn (?User $record): array => self::employeeOptions($record))
                    ->searchable()
                    ->required(fn (Get $get): bool => self::role($get) === UserRole::Employee)
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

    /**
     * Employees who earn leave credits, as "Dela Cruz, Juan S. — 2020-0001".
     * The current link is offered even when that employee has left, so the
     * account can still be saved, say to deactivate it.
     *
     * @return array<int, string>
     */
    private static function employeeOptions(?User $record): array
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return Employee::withTrashed()
            ->where(fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at')->whereIn('employment_status', $earning))
            ->orWhere('id', $record?->employee_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->full_name} — {$employee->employee_number}"])
            ->all();
    }

    private static function role(Get $get): ?UserRole
    {
        $state = $get('role');

        return $state instanceof UserRole ? $state : UserRole::tryFrom((string) $state);
    }
}
