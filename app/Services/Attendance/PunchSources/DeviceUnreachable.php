<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use RuntimeException;
use Throwable;

/**
 * The device could not be read: off, unplugged, a wrong IP, port or comm key.
 */
class DeviceUnreachable extends RuntimeException
{
    public static function because(Device $device, Throwable $previous): self
    {
        return new self(
            "Cannot reach {$device->ip}:{$device->port}: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
