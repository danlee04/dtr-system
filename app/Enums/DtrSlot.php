<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The four time columns of the DTR form, in the order of the day. Each value
 * is also the `dtr_days` column that holds what IT printed under it.
 */
enum DtrSlot: string implements HasLabel
{
    case AmArrival = 'am_arrival';
    case AmDeparture = 'am_departure';
    case PmArrival = 'pm_arrival';
    case PmDeparture = 'pm_departure';

    public function getLabel(): string
    {
        return match ($this) {
            self::AmArrival => 'A.M. arrival',
            self::AmDeparture => 'A.M. departure',
            self::PmArrival => 'P.M. arrival',
            self::PmDeparture => 'P.M. departure',
        };
    }
}
