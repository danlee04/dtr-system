<?php

namespace Tests\Feature\Devices;

use App\Services\Attendance\PunchSources\DeviceTime;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

class DeviceTimeTest extends TestCase
{
    public function test_a_device_reading_is_manila_time_whatever_zone_php_is_in(): void
    {
        // The library may hand back 07:58 tagged UTC. It still means 07:58 on
        // the device's screen, in Manila.
        $reading = new DateTimeImmutable('2026-10-06 07:58:00', new DateTimeZone('UTC'));

        $time = DeviceTime::read($reading);

        $this->assertSame('2026-10-06 07:58:00', $time->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $time->getTimezone()->getName());
    }

    public function test_setting_the_clock_writes_manila_wall_clock_time(): void
    {
        $midnightUtc = CarbonImmutable::parse('2026-10-06 00:00:00', 'UTC');

        $this->assertSame('2026-10-06 08:00:00', DeviceTime::write($midnightUtc)->format('Y-m-d H:i:s'));
    }
}
