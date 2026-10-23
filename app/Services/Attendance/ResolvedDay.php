<?php

namespace App\Services\Attendance;

use App\Enums\DayKind;
use App\Models\Schedule;

/**
 * One date for one employee. The schedule is set on a work day: the roster's
 * or the assignment's, whichever applied.
 */
final readonly class ResolvedDay
{
    public function __construct(
        public DayKind $kind,
        public ?Schedule $schedule = null,
    ) {}
}
