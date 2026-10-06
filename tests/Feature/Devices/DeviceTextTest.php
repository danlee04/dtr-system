<?php

namespace Tests\Feature\Devices;

use App\Services\Attendance\PunchSources\DeviceText;
use Tests\TestCase;

class DeviceTextTest extends TestCase
{
    public function test_a_name_stored_in_windows_1252_reads_as_utf8(): void
    {
        // Devices keep names in their own codepage. "PEÑAFLOR" has to reach
        // MySQL as valid UTF-8, or every sync that saves device names fails.
        $stored = mb_convert_encoding('PEÑAFLOR, JUAN', 'Windows-1252', 'UTF-8');

        $this->assertSame('PEÑAFLOR, JUAN', DeviceText::read($stored));
    }

    public function test_a_utf8_name_is_left_alone(): void
    {
        $this->assertSame('NIÑO REYES', DeviceText::read('NIÑO REYES'));
    }

    public function test_the_padding_of_the_devices_name_field_is_removed(): void
    {
        $this->assertSame('ANA CRUZ', DeviceText::read("ANA CRUZ \0\0\0"));
    }
}
