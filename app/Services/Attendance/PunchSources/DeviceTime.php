<?php

namespace App\Services\Attendance\PunchSources;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A biometric device keeps wall-clock time with no zone: 07:58 on its screen
 * is 07:58 in Manila. Reading that through a PHP default zone that happens to
 * be UTC would put every punch, and every late, eight hours off.
 */
final class DeviceTime
{
    /** The device's wall-clock reading, as Manila time. */
    public static function read(DateTimeInterface $deviceTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $deviceTime->format('Y-m-d H:i:s'),
            config('app.timezone'),
        );
    }

    /** What the device's clock should show for this moment. */
    public static function write(CarbonImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }
}
