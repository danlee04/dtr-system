<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Closure;
use ZkTeco\Exceptions\ZkException;
use ZkTeco\TCP\Device as ZkDevice;
use ZkTeco\Values\AttendanceRecord;
use ZkTeco\Values\User as ZkUser;

/**
 * Reads a ZKTeco device over TCP through msaied/zkteco.
 *
 * Every call opens its own connection and closes it. None of them disables
 * the device, so people can keep punching while it is read, and nothing here
 * ever clears the device's logs.
 */
class ZktecoPunchSource implements PunchSource
{
    public function fetchPunches(Device $device): iterable
    {
        return $this->connected($device, fn (ZkDevice $zk): array => array_map(
            fn (AttendanceRecord $record): RawPunch => new RawPunch(
                biometricId: $record->userId,
                punchedAt: DeviceTime::read($record->recordedAt),
                rawState: $record->punchState->value,
            ),
            $zk->attendance()->all(),
        ));
    }

    public function deviceTime(Device $device): CarbonImmutable
    {
        return $this->connected(
            $device,
            fn (ZkDevice $zk): CarbonImmutable => DeviceTime::read($zk->info()->time()),
        );
    }

    public function setDeviceTime(Device $device, CarbonImmutable $time): void
    {
        $this->connected($device, function (ZkDevice $zk) use ($time): void {
            $zk->info()->setTime(DeviceTime::write($time));
        });
    }

    public function deviceUsers(Device $device): iterable
    {
        return $this->connected($device, fn (ZkDevice $zk): array => array_map(
            fn (ZkUser $user): DeviceUser => new DeviceUser(
                biometricId: $user->userId,
                name: trim($user->name),
            ),
            $zk->users()->all(),
        ));
    }

    /**
     * @template TResult
     *
     * @param  Closure(ZkDevice): TResult  $work
     * @return TResult
     */
    private function connected(Device $device, Closure $work): mixed
    {
        $zk = new ZkDevice(
            host: $device->ip,
            port: $device->port,
            commKey: (int) ($device->comm_key ?? 0),
            timeout: 10.0,
        );

        try {
            $zk->connect();

            return $work($zk);
        } catch (ZkException $exception) {
            throw DeviceUnreachable::because($device, $exception);
        } finally {
            try {
                $zk->disconnect();
            } catch (ZkException) {
                // The connection is already gone; there is nothing to close.
            }
        }
    }
}
