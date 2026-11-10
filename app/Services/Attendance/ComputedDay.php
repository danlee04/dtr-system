<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\DtrSlot;

/**
 * One date of a DTR, judged against the schedule: minutes late and under,
 * how many halves of the day each happened in, and the taps it lacks.
 */
final readonly class ComputedDay
{
    /**
     * @param  float  $absent  days: 1, or 0.5 when the other half is on leave or an OB
     * @param  list<string>  $missing  the taps the day lacks, e.g. "lunch in"
     * @param  ?string  $note  what covers part of the day, e.g. "Official business (afternoon)"
     * @param  list<DtrSlot>  $fillable  the blanks HR may fill from the signed DTR: the missing taps, or every slot of a half with none
     */
    public function __construct(
        public AttendanceStatus $status,
        public int $late = 0,
        public int $lateCount = 0,
        public int $undertime = 0,
        public int $undertimeCount = 0,
        public float $absent = 0.0,
        public array $missing = [],
        public ?string $note = null,
        public array $fillable = [],
    ) {}
}
