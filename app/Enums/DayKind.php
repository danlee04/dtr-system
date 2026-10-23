<?php

namespace App\Enums;

/**
 * What a date is for one employee, before any punch is read: the answer
 * ScheduleResolver gives for leave counting now and for the DTR later.
 */
enum DayKind: string
{
    case NotEmployed = 'not_employed';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';
    case Suspended = 'suspended';
    case WorkDay = 'work_day';
    case NoSchedule = 'no_schedule';
}
