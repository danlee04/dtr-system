<?php

namespace Tests\Feature\Devices;

use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakePunchSource;
use Tests\TestCase;
use ZkTeco\Laravel\ZkTecoServiceProvider;

class ProbeDeviceTest extends TestCase
{
    private function punch(string $biometricId, string $at, int $state = 0): RawPunch
    {
        return new RawPunch($biometricId, CarbonImmutable::parse($at), $state);
    }

    public function test_it_prints_the_clock_the_users_and_the_ten_latest_logs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00'));

        $punches = [];
        for ($minute = 0; $minute < 12; $minute++) {
            $punches[] = $this->punch('0042', sprintf('2026-10-06 07:%02d:00', 40 + $minute));
        }

        $this->app->instance(PunchSource::class, new FakePunchSource(
            punches: $punches,
            users: [new DeviceUser('0042', 'JUAN DELA CRUZ'), new DeviceUser('0007', 'ANA REYES')],
            clock: CarbonImmutable::parse('2026-10-06 08:00:30'),
        ));

        $latestTen = [];
        for ($minute = 11; $minute >= 2; $minute--) {
            $latestTen[] = ['0042', 'JUAN DELA CRUZ', sprintf('2026-10-06 07:%02d:00', 40 + $minute), '0'];
        }

        $this->artisan('device:probe', ['ip' => '192.168.1.201'])
            ->expectsOutputToContain('Device clock: 2026-10-06 08:00:30 (30 seconds ahead of this server)')
            ->expectsOutputToContain('Users on device: 2')
            ->expectsOutputToContain('Logs on device: 12')
            ->expectsTable(['Biometric ID', 'Name on device', 'Time', 'State'], $latestTen)
            ->assertSuccessful();
    }

    public function test_a_device_that_cannot_be_reached_fails_with_the_reason(): void
    {
        $this->app->instance(PunchSource::class, FakePunchSource::unreachable('Connection timed out'));

        $this->artisan('device:probe', ['ip' => '192.168.1.201', '--port' => 4370])
            ->expectsOutputToContain('Cannot reach 192.168.1.201:4370: Connection timed out')
            ->assertFailed();
    }

    public function test_the_application_reads_devices_through_the_zkteco_library(): void
    {
        $this->assertInstanceOf(ZktecoPunchSource::class, $this->app->make(PunchSource::class));
    }

    public function test_the_librarys_own_laravel_bridge_is_not_loaded(): void
    {
        // Its routes and commands are for ADMS push, which this system does
        // not use. Not loading it means no endpoint the device could call.
        $this->assertArrayNotHasKey(ZkTecoServiceProvider::class, $this->app->getLoadedProviders());
    }
}
