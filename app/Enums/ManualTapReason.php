<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a slot has a time HR copied in from the signed paper: a blank the DTR
 * printed, or a time the device got wrong.
 */
enum ManualTapReason: string implements HasLabel
{
    case ForgotToTap = 'forgot_to_tap';
    case OfficialBusiness = 'official_business';
    case DeviceError = 'device_error';
    case GatePass = 'gate_pass';

    public function getLabel(): string
    {
        return match ($this) {
            self::ForgotToTap => 'Forgot to tap',
            self::OfficialBusiness => 'Out on official business',
            self::DeviceError => 'Biometric error',
            self::GatePass => 'Gate pass',
        };
    }
}
