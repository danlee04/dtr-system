<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a date on an uploaded DTR comes to. The days that are not work days
 * keep DayKind's values, and an OB or travel order keeps ExceptionType's, so
 * either maps across by value.
 */
enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Leave = 'leave';
    case OfficialBusiness = 'official_business';
    case TravelOrder = 'travel_order';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';
    case Suspended = 'suspended';
    case NotEmployed = 'not_employed';
    case NoSchedule = 'no_schedule';
    case Upcoming = 'upcoming';
    case NotComputed = 'not_computed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Leave => 'Leave',
            self::OfficialBusiness => 'Official business',
            self::TravelOrder => 'Travel order',
            self::RestDay => 'Rest day',
            self::Holiday => 'Holiday',
            self::Suspended => 'Suspended',
            self::NotEmployed => 'Not employed',
            self::NoSchedule => 'No schedule',
            self::Upcoming => 'Not yet',
            self::NotComputed => 'Night shift: not computed yet',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Leave, self::OfficialBusiness, self::TravelOrder => 'info',
            self::NoSchedule, self::NotComputed => 'warning',
            default => 'gray',
        };
    }
}
