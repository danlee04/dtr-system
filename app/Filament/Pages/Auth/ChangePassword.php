<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Schema;

/**
 * Everyone's own password, from the user menu. Names and emails stay the
 * admin's business, on the Users screen.
 */
class ChangePassword extends EditProfile
{
    protected static ?string $title = 'Change Password';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPasswordFormComponent()->required(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
