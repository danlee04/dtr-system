<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A part of the day HR excuses from late, undertime and absence, on a signed
 * paper: work away from the office (an OB or a travel order), a gate pass, or
 * leave HR marks by hand while the leave module is not yet in use (HR,
 * 2026-11-19). Such leave is a mark on the DTR only: it takes no credits and
 * needs no application.
 */
enum ExceptionType: string implements HasLabel
{
    case OfficialBusiness = 'official_business';
    case TravelOrder = 'travel_order';
    case Leave = 'leave';
    case GatePass = 'gate_pass';

    public function getLabel(): string
    {
        return match ($this) {
            self::OfficialBusiness => 'Official business',
            self::TravelOrder => 'Travel order',
            self::Leave => 'Leave',
            self::GatePass => 'Gate pass',
        };
    }
}
