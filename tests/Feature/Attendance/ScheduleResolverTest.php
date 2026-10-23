<?php

namespace Tests\Feature\Attendance;

use App\Enums\DayKind;
use App\Enums\HolidayType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\ResolvedDay;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 1 November 2026 is a Sunday; 30 November is Bonifacio Day.
 */
class ScheduleResolverTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Schedule $regular;

    private Schedule $night;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = Employee::factory()->create();
        $this->regular = Schedule::factory()->create(['code' => 'REG']);
        $this->night = Schedule::factory()->rosterOnly()->create(['code' => 'N']);

        ScheduleAssignment::factory()->for($this->employee)->for($this->regular)->create(['effective_from' => '2026-11-01']);
    }

    public function test_a_weekday_on_a_fixed_schedule_is_a_work_day(): void
    {
        $day = $this->day('2026-11-03');

        $this->assertSame(DayKind::WorkDay, $day->kind);
        $this->assertTrue($day->schedule->is($this->regular));
    }

    public function test_a_weekend_on_a_fixed_schedule_is_a_rest_day(): void
    {
        $this->assertSame(DayKind::RestDay, $this->day('2026-11-07')->kind);
    }

    public function test_holidays_and_suspensions_on_a_fixed_schedule(): void
    {
        Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
        Holiday::factory()->create(['date' => '2026-11-04', 'type' => HolidayType::Suspension]);
        Holiday::factory()->create(['date' => '2026-11-05', 'type' => HolidayType::Suspension, 'suspension_starts_at' => '15:00:00']);

        $this->assertSame(DayKind::Holiday, $this->day('2026-11-30')->kind);
        $this->assertSame(DayKind::Suspended, $this->day('2026-11-04')->kind);
        $this->assertSame(DayKind::WorkDay, $this->day('2026-11-05')->kind);
    }

    public function test_the_roster_wins_over_the_assignment(): void
    {
        RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-07', 'schedule_id' => $this->night->id]);
        RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-03', 'schedule_id' => null]);

        $this->assertTrue($this->day('2026-11-07')->schedule->is($this->night));
        $this->assertSame(DayKind::RestDay, $this->day('2026-11-03')->kind);
    }

    public function test_a_rostered_nurse_works_on_a_holiday(): void
    {
        Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
        RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-30', 'schedule_id' => $this->night->id]);

        $this->assertSame(DayKind::WorkDay, $this->day('2026-11-30')->kind);
    }

    public function test_a_date_with_neither_has_no_schedule(): void
    {
        $this->assertSame(DayKind::NoSchedule, $this->day('2026-10-30')->kind);
    }

    public function test_an_ended_assignment_no_longer_applies(): void
    {
        $this->employee->scheduleAssignments()->sole()->update(['effective_to' => '2026-11-15']);

        $this->assertSame(DayKind::NoSchedule, $this->day('2026-11-16')->kind);
    }

    public function test_dates_outside_employment(): void
    {
        $this->employee->update(['date_hired' => '2026-11-02', 'date_separated' => '2026-11-20']);

        $this->assertSame(DayKind::NotEmployed, $this->day('2026-11-01')->kind);
        $this->assertSame(DayKind::NotEmployed, $this->day('2026-11-21')->kind);
    }

    public function test_every_date_in_the_range_comes_back_in_order(): void
    {
        $days = app(ScheduleResolver::class)->days($this->employee, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-07'));

        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'], array_keys($days));
    }

    private function day(string $date): ResolvedDay
    {
        $at = CarbonImmutable::parse($date);

        return app(ScheduleResolver::class)->days($this->employee->fresh(), $at, $at)[$date];
    }
}
