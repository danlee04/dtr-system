<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Enums\UserRole;
use App\Models\Employee;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * A login for each selected employee who earns leave credits, still works
 * here and has none yet. Each gets a temporary password, shown once on the
 * slips page for HR to print and hand out.
 */
class CreateLoginsBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'createLogins';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Create logins')
            ->icon(Heroicon::OutlinedKey)
            ->authorize('manageLogin')
            ->requiresConfirmation()
            ->modalDescription('Each selected employee who earns leave credits, is active and has no login yet gets one, with a temporary password that is shown once, for you to print.')
            // All or none: a login whose slip never showed is a password nobody saw.
            ->databaseTransaction()
            ->action(function (Collection $records): void {
                $slips = [];

                foreach ($records->loadMissing('user') as $employee) {
                    if (! $this->qualifies($employee)) {
                        continue;
                    }

                    $password = Str::password(10, symbols: false);

                    $employee->user()->create([
                        'name' => $employee->full_name,
                        'password' => $password,
                        'role' => UserRole::Employee,
                        'must_change_password' => true,
                    ]);

                    $slips[] = [
                        'name' => $employee->full_name,
                        'employee_number' => $employee->employee_number,
                        'password' => $password,
                    ];
                }

                $created = count($slips);
                $skipped = $records->count() - $created;
                $skippedNote = "{$skipped} skipped (no credits, inactive, or already has one).";

                if ($created === 0) {
                    Notification::make()
                        ->warning()
                        ->title('No logins created')
                        ->body($skippedNote)
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("{$created} ".str('login')->plural($created).' created')
                    ->body($skipped > 0 ? $skippedNote : null)
                    ->send();

                session()->put('login_slips', encrypt($slips));

                $this->redirect(route('employee-logins.slips'), navigate: false);
            })
            ->deselectRecordsAfterCompletion();
    }

    private function qualifies(Employee $employee): bool
    {
        return $employee->employment_status->earnsLeaveCredits()
            && $employee->is_active
            && ! $employee->trashed()
            && $employee->user === null;
    }
}
