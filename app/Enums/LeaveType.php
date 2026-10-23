<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The leave types of CS Form No. 6, in the form's order, with Wellness Leave
 * added. They follow CSC rules, so a new type is a code change. Only four
 * carry a balance; the rest are recorded and deduct nothing.
 */
enum LeaveType: string implements HasLabel
{
    case Vacation = 'VL';
    case Forced = 'FL';
    case Sick = 'SL';
    case Maternity = 'ML';
    case Paternity = 'PL';
    case SpecialPrivilege = 'SPL';
    case SoloParent = 'SOLO';
    case Study = 'STUDY';
    case Vawc = 'VAWC';
    case Rehabilitation = 'RP';
    case SpecialLeaveBenefitsForWomen = 'SLBW';
    case SpecialEmergency = 'SEL';
    case Adoption = 'ADOPT';
    case Wellness = 'WL';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vacation => 'Vacation Leave',
            self::Forced => 'Mandatory/Forced Leave',
            self::Sick => 'Sick Leave',
            self::Maternity => 'Maternity Leave',
            self::Paternity => 'Paternity Leave',
            self::SpecialPrivilege => 'Special Privilege Leave',
            self::SoloParent => 'Solo Parent Leave',
            self::Study => 'Study Leave',
            self::Vawc => '10-Day VAWC Leave',
            self::Rehabilitation => 'Rehabilitation Privilege',
            self::SpecialLeaveBenefitsForWomen => 'Special Leave Benefits for Women',
            self::SpecialEmergency => 'Special Emergency (Calamity) Leave',
            self::Adoption => 'Adoption Leave',
            self::Wellness => 'Wellness Leave',
        };
    }

    /**
     * The types with a balance, in the order balances are shown. Forced
     * leave has none of its own: it draws from VL.
     *
     * @return list<self>
     */
    public static function tracked(): array
    {
        return [self::Vacation, self::Sick, self::SpecialPrivilege, self::Wellness];
    }

    public function isTracked(): bool
    {
        return in_array($this, self::tracked(), true);
    }
}
