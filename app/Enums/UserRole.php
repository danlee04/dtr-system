<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an account may do. Both roles run the attendance work; only an admin
 * manages accounts and the biometric devices.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Hr = 'hr';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Hr => 'HR',
        };
    }
}
