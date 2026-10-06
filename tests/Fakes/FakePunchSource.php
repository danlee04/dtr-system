<?php

namespace Tests\Fakes;

use App\Models\Device;
use App\Services\Attendance\PunchSources\DeviceUnreachable;
use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A device held in memory. It remembers the clock it was told to set, and it
 * can be made unreachable.
 */
class FakePunchSource implements PunchSource
{
    public ?CarbonImmutable $timeSet = null;

    /**
     * @param  list<RawPunch>  $punches
     * @param  list<DeviceUser>  $users
     */
    public function __construct(
        public array $punches = [],
        public array $users = [],
        public ?CarbonImmutable $clock = null,
        public ?string $failure = null,
    ) {}

    public static function unreachable(string $reason = 'Connection timed out'): self
    {
        return new self(failure: $reason);
    }

    public function fetchPunches(Device $device): iterable
    {
        $this->failIfUnreachable($device);

        return $this->punches;
    }

    public function deviceTime(Device $device): CarbonImmutable
    {
        $this->failIfUnreachable($device);

        return $this->clock ?? CarbonImmutable::now();
    }

    public function setDeviceTime(Device $device, CarbonImmutable $time): void
    {
        $this->failIfUnreachable($device);

        $this->timeSet = $time;
        $this->clock = $time;
    }

    public function deviceUsers(Device $device): iterable
    {
        $this->failIfUnreachable($device);

        return $this->users;
    }

    private function failIfUnreachable(Device $device): void
    {
        if ($this->failure !== null) {
            throw DeviceUnreachable::because($device, new RuntimeException($this->failure));
        }
    }
}
