<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Attendance\PunchSources\DeviceUnreachable;
use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Proves the device can be read before anything is built on it: its clock,
 * how many people are enrolled, and its ten latest logs. It only reads; it
 * never changes the device.
 */
class ProbeDevice extends Command
{
    protected $signature = 'device:probe
                            {ip : The device\'s IP address}
                            {--port=4370 : The device\'s TCP port}
                            {--key=0 : The comm key set on the device, 0 if none}';

    protected $description = 'Read a biometric device\'s clock, users and latest logs without changing anything';

    public function handle(PunchSource $source): int
    {
        // Not saved: the probe runs before the device is registered.
        $device = new Device([
            'name' => 'Probe',
            'ip' => $this->argument('ip'),
            'port' => (int) $this->option('port'),
            'comm_key' => (string) $this->option('key'),
        ]);

        try {
            $clock = $source->deviceTime($device);
            // Taken now, before the slow reads, so their time is not counted as drift.
            $serverTime = CarbonImmutable::now();
            $users = collect($source->deviceUsers($device));
            $punches = collect($source->fetchPunches($device));
        } catch (DeviceUnreachable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $namesById = $users->mapWithKeys(fn (DeviceUser $user): array => [$user->biometricId => $user->name]);
        $drift = (int) round($serverTime->diffInSeconds($clock));

        $this->line("Device: {$device->ip}:{$device->port}");
        $this->line("Device clock: {$clock->format('Y-m-d H:i:s')} ({$this->describeDrift($drift)})");
        $this->line("Users on device: {$users->count()}");
        $this->line("Logs on device: {$punches->count()}");

        $this->table(
            ['Biometric ID', 'Name on device', 'Time', 'State'],
            $punches
                ->sortByDesc(fn (RawPunch $punch): int => $punch->punchedAt->getTimestamp())
                ->take(10)
                ->map(fn (RawPunch $punch): array => [
                    $punch->biometricId,
                    $namesById->get($punch->biometricId, ''),
                    $punch->punchedAt->format('Y-m-d H:i:s'),
                    (string) $punch->rawState,
                ])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }

    private function describeDrift(int $seconds): string
    {
        return match (true) {
            $seconds === 0 => 'same as this server',
            $seconds > 0 => "{$seconds} seconds ahead of this server",
            default => abs($seconds).' seconds behind this server',
        };
    }
}
