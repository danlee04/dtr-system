<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Everyone's own profile, from the user menu: an employee account's photo,
 * and the password. Names and emails stay the admin's business, on the Users
 * screen.
 */
class ChangePassword extends EditProfile
{
    protected static ?string $title = 'Profile';

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = $this->getUser();
        $hasEmployee = $user->employee !== null;

        return $schema
            ->components([
                // Saved on their employee record. Hidden without one, or saving would make one.
                Section::make('Photo')
                    ->relationship('employee')
                    ->visible($hasEmployee)
                    ->schema([
                        EmployeeForm::photoField()
                            ->hiddenLabel(),
                    ]),
                Section::make('Password')
                    ->schema([
                        // An employee may save only a photo; for staff this page is the password.
                        $this->getPasswordFormComponent()->required(! $hasEmployee),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
