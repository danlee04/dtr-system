<?php

namespace App\Services\Attendance\PunchSources;

use Carbon\CarbonImmutable;

/**
 * One log exactly as the device recorded it, in Manila wall-clock time.
 */
final readonly class RawPunch
{
    public function __construct(
        public string $biometricId,
        public CarbonImmutable $punchedAt,
        public ?int $rawState = null,
    ) {}
}
