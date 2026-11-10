<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a slot the DTR printed blank has a time HR copied in from the signed
 * paper.
 */
enum ManualTapReason: string implements HasLabel
{
    case ForgotToTap = 'forgot_to_tap';
    case OfficialBusiness = 'official_business';

    public function getLabel(): string
    {
        return match ($this) {
            self::ForgotToTap => 'Forgot to tap',
            self::OfficialBusiness => 'Out on official business',
        };
    }
}
