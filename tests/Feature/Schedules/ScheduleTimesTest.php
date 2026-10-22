<?php

namespace Tests\Feature\Schedules;

use App\Services\Attendance\ScheduleTimes;
use Tests\TestCase;

class ScheduleTimesTest extends TestCase
{
    public function test_times_later_in_the_day_follow_on(): void
    {
        $this->assertSame([0, 240, 300, 540], ScheduleTimes::offsets(['08:00', '12:00', '13:00', '17:00']));
    }

    public function test_a_time_earlier_than_the_one_before_falls_on_the_next_day(): void
    {
        // Night: 22:00, 02:00, 03:00, 07:00. HR never ticks a "next day" box.
        $this->assertSame([0, 240, 300, 540], ScheduleTimes::offsets(['22:00', '02:00', '03:00', '07:00']));
    }

    public function test_a_time_equal_to_the_one_before_is_a_whole_day_later(): void
    {
        $this->assertSame([0, 1440], ScheduleTimes::offsets(['08:00:00', '08:00:00']));
    }
}
