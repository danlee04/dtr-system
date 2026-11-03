<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;

/**
 * One date of a DTR, judged against the schedule: minutes late and under,
 * how many halves of the day each happened in, and the taps it lacks.
 */
final readonly class ComputedDay
{
    /**
     * @param  float  $absent  days: 1, or 0.5 when the other half is on leave
     * @param  list<string>  $missing  the taps the day lacks, e.g. "lunch in"
     */
    public function __construct(
        public AttendanceStatus $status,
        public int $late = 0,
        public int $lateCount = 0,
        public int $undertime = 0,
        public int $undertimeCount = 0,
        public float $absent = 0.0,
        public array $missing = [],
    ) {}
}
