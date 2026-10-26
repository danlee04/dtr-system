<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an account may do. Admin and HR are staff and use HR's screens; only
 * an admin manages accounts and the biometric devices. An employee account
 * sees only its own leave, under My leave.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Hr = 'hr';
    case Employee = 'employee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Hr => 'HR',
            self::Employee => 'Employee',
        };
    }
}
