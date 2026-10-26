<?php

namespace App\Filament\Employee\Pages\Auth;

use App\Models\Employee;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Sign-in by employee number instead of email. Filament's rate limit and
 * timebox still apply, and every failure reads the same, so the form never
 * tells which numbers have an account.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('employee_number')
                    ->label('Employee number')
                    ->required()
                    ->autocomplete('username')
                    ->autofocus(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $employeeId = Employee::query()
            ->where('employee_number', trim($data['employee_number']))
            ->value('id');

        return [
            // Never null: that would match every account with no employee.
            'employee_id' => $employeeId ?? 0,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.employee_number' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
