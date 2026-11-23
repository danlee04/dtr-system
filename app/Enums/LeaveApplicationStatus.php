<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Pending from filing until HR marks the signed form approved or disapproves
 * it; cancelled when it is withdrawn. Disapproved and cancelled give the days
 * back.
 */
enum LeaveApplicationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
    // Not approved by HR, with the reason (HR, 2026-11-23); before, that was a cancellation.
    case Disapproved = 'disapproved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
            self::Disapproved => 'Disapproved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Cancelled => 'gray',
            self::Disapproved => 'danger',
        };
    }

    /**
     * The statuses whose days stand: they are drawn from the balances, cover
     * the DTR and block another application on the same dates. A cancelled or
     * disapproved one gave its days back.
     *
     * @return list<self>
     */
    public static function standing(): array
    {
        return [self::Pending, self::Approved];
    }

    public function stands(): bool
    {
        return in_array($this, self::standing(), true);
    }
}
