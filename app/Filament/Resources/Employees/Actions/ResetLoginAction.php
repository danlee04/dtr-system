<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Enums\UserRole;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * A new temporary password for an employee who forgot theirs, shown once on
 * the slips page. They must change it when they next sign in.
 */
class ResetLoginAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetLogin';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reset login password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (Employee $record): bool => $record->user?->role === UserRole::Employee)
            ->authorize('manageLogin')
            ->requiresConfirmation()
            ->modalDescription('Their current password stops working. They get a temporary one, shown once for you to print, and must change it when they next sign in.')
            ->action(function (Employee $record): void {
                $password = Str::password(10, symbols: false);

                $record->user->update([
                    'password' => $password,
                    'must_change_password' => true,
                ]);

                session()->put('login_slips', encrypt([[
                    'name' => $record->full_name,
                    'employee_number' => $record->employee_number,
                    'password' => $password,
                ]]));

                $this->redirect(route('employee-logins.slips'), navigate: false);
            });
    }
}
