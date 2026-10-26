<?php

namespace App\Filament\Employee\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * The only thing an employee changes about their account. HR hands out a
 * temporary password, and RequirePasswordChange keeps them here until it is
 * replaced.
 */
class ChangePassword extends EditProfile
{
    protected static ?string $title = 'Change password';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPasswordFormComponent()->required(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        return [...$data, 'must_change_password' => false];
    }

    /** Back to their leave once the new password is set. */
    protected function getRedirectUrl(): ?string
    {
        return filament()->getUrl();
    }
}
