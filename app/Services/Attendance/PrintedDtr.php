<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

/**
 * One employee's DTR as the IT Unit printed it, before anything is judged:
 * the name as printed, the dates it covers, and what each day's row shows.
 */
final readonly class PrintedDtr
{
    /**
     * @param  array<string, array<string, string>>  $days  each date with anything printed (Y-m-d), to its entries by
     *                                                      column: am_arrival … ot_departure as H:i, and remarks
     */
    public function __construct(
        public string $employeeName,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public array $days,
    ) {}
}
