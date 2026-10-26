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

    /** What an employee reads on the Leave types page. HR reviews this copy before go-live. */
    public function description(): string
    {
        return match ($this) {
            self::Vacation => 'Time off for personal reasons, rest or travel. File it ahead of time. Taken from your VL credits.',
            self::Forced => 'The five VL days a year everyone with 10 or more VL credits must take. Days not taken by December 31 are deducted from VL.',
            self::Sick => 'For your own illness, injury or check-up. File it when you return; more than five days needs a medical certificate. Taken from SL, then VL.',
            self::Maternity => '105 days with pay for childbirth or miscarriage (R.A. No. 11210). Ask HR about the requirements.',
            self::Paternity => "7 days for a married male employee for each of his wife's first four deliveries (R.A. No. 8187).",
            self::SpecialPrivilege => "3 days a year for personal milestones and family obligations, such as a birthday, an enrollment or a family member's hospitalization. Unused days do not carry over.",
            self::SoloParent => '7 days a year for a solo parent with a valid Solo Parent ID (R.A. No. 8972).',
            self::Study => "Up to six months to finish a master's degree or review for the bar or a board examination, under an agreement with the hospital.",
            self::Vawc => 'Up to 10 days for an employee who is a victim of violence under R.A. No. 9262, to attend to medical and legal concerns.',
            self::Rehabilitation => 'Up to six months for an employee injured in the performance of duty, to recover.',
            self::SpecialLeaveBenefitsForWomen => 'Up to two months for a woman employee after surgery for a gynecological disorder (R.A. No. 9710).',
            self::SpecialEmergency => 'Up to 5 days a year for an employee directly affected by a calamity in a declared area.',
            self::Adoption => 'For an employee who legally adopts a child (R.A. No. 8552). Ask HR about the days and requirements.',
            self::Wellness => '5 days a year for rest and wellness. Unused days do not carry over.',
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
