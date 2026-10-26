<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an account may do. Admin and HR are staff and run the HR panel; only
 * an admin manages accounts and the biometric devices. An employee account
 * reaches only the employee panel, and only its own leave.
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
