<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The choices under item 6.B, "Details of leave", of CS Form No. 6. Which
 * ones apply depends on the leave type.
 */
enum LeaveDetail: string implements HasLabel
{
    case WithinPhilippines = 'within_philippines';
    case Abroad = 'abroad';
    case InHospital = 'in_hospital';
    case OutPatient = 'out_patient';
    case MastersDegree = 'masters_degree';
    case BoardReview = 'board_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::WithinPhilippines => 'Within the Philippines',
            self::Abroad => 'Abroad',
            self::InHospital => 'In hospital',
            self::OutPatient => 'Out patient',
            self::MastersDegree => "Completion of master's degree",
            self::BoardReview => 'BAR/board examination review',
        };
    }

    /** @return list<self> */
    public static function forType(LeaveType $type): array
    {
        return match ($type) {
            LeaveType::Vacation, LeaveType::SpecialPrivilege => [self::WithinPhilippines, self::Abroad],
            LeaveType::Sick => [self::InHospital, self::OutPatient],
            LeaveType::Study => [self::MastersDegree, self::BoardReview],
            default => [],
        };
    }

    /** The form asks to specify: the country, or the illness. */
    public function needsText(): bool
    {
        return in_array($this, [self::Abroad, self::InHospital, self::OutPatient], true);
    }
}
