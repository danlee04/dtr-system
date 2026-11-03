<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;

/**
 * A DTR's dates, judged, and what they add up to for the month.
 */
final readonly class ComputedDtr
{
    public int $late;

    public int $lateCount;

    public int $undertime;

    public int $undertimeCount;

    public float $absent;

    public int $daysMissingTaps;

    public int $daysWithoutSchedule;

    /** Late and undertime as days of leave, by the CSC table; null for staff who earn no leave credits. */
    public ?string $dayEquivalent;

    /** @param  array<string, ComputedDay>  $days  Y-m-d → day */
    public function __construct(public array $days, bool $earnsLeaveCredits)
    {
        $this->late = array_sum(array_column($days, 'late'));
        $this->lateCount = array_sum(array_column($days, 'lateCount'));
        $this->undertime = array_sum(array_column($days, 'undertime'));
        $this->undertimeCount = array_sum(array_column($days, 'undertimeCount'));
        $this->absent = (float) array_sum(array_column($days, 'absent'));
        $this->daysMissingTaps = count(array_filter($days, fn (ComputedDay $day): bool => $day->missing !== []));
        $this->daysWithoutSchedule = count(array_filter($days, fn (ComputedDay $day): bool => $day->status === AttendanceStatus::NoSchedule));
        $this->dayEquivalent = $earnsLeaveCredits ? CscConversion::days($this->late + $this->undertime) : null;
    }
}
