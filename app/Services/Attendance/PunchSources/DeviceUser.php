<?php

namespace App\Services\Attendance\PunchSources;

/**
 * A person enrolled on a device: the ID they punch with and the name typed
 * into the device when they were enrolled.
 */
final readonly class DeviceUser
{
    public function __construct(
        public string $biometricId,
        public string $name,
    ) {}
}
