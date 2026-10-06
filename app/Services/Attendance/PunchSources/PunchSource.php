<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use Carbon\CarbonImmutable;

/**
 * Everything the system needs from a biometric device, and nothing more.
 *
 * Sync, the probe and the Devices screen talk to this, never to a vendor
 * library, so a device that needs another library — or a USB file import —
 * is a new implementation rather than a rewrite.
 *
 * Every method throws DeviceUnreachable when the device cannot be read.
 */
interface PunchSource
{
    /** @return iterable<RawPunch> */
    public function fetchPunches(Device $device): iterable;

    public function deviceTime(Device $device): CarbonImmutable;

    public function setDeviceTime(Device $device, CarbonImmutable $time): void;

    /** @return iterable<DeviceUser> */
    public function deviceUsers(Device $device): iterable;
}
