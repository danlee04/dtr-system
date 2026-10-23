<?php

namespace Tests\Feature\Leave;

use App\Enums\HolidayType;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Leave\LeaveDayCounter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveDayCounterTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Schedule $regular;

    private Schedule $fourDay;

    private Schedule $duty16;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 08:00');

        $this->employee = Employee::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
        $this->regular = Schedule::factory()->create(['code' => 'REG']);
        $this->fourDay = Schedule::factory()->create(['code' => 'CWW', 'work_days' => [1, 2, 3, 4], 'leave_days' => '1.250']);
        $this->duty16 = Schedule::factory()->rosterOnly()->create(['code' => 'D16']);
    }

    public function test_a_regular_day_is_one(): void
    {
        $this->assign($this->regular);

        $this->assertSame(['2026-11-03' => '1.000', '2026-11-04' => '1.000'], $this->countDays('VL', '2026-11-03', '2026-11-04'));
    }

    public function test_a_four_day_week_day_is_one_and_a_quarter(): void
    {
        $this->assign($this->fourDay);

        $this->assertSame(['2026-11-03' => '1.250'], $this->countDays('VL', '2026-11-03'));
    }

    public function test_a_16_hour_duty_is_one(): void
    {
        RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-07', 'schedule_id' => $this->duty16->id]);

        $this->assertSame(['2026-11-07' => '1.000'], $this->countDays('SL', '2026-11-07'));
    }

    public function test_spl_and_wellness_are_one_a_day_on_any_schedule(): void
    {
        $this->assign($this->fourDay);

        $this->assertSame(['2026-11-03' => '1.000'], $this->countDays('SPL', '2026-11-03'));
        $this->assertSame(['2026-11-03' => '1.000'], $this->countDays('WL', '2026-11-03'));
    }

    public function test_rest_days_and_holidays_count_zero(): void
    {
        $this->assign($this->regular);
        Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);

        $this->assertSame(
            ['2026-11-27' => '1.000', '2026-11-28' => '0.000', '2026-11-29' => '0.000', '2026-11-30' => '0.000'],
            $this->countDays('VL', '2026-11-27', '2026-11-30'),
        );
    }

    public function test_a_rostered_duty_on_a_holiday_counts(): void
    {
        Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
        RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-30', 'schedule_id' => $this->duty16->id]);

        $this->assertSame(['2026-11-30' => '1.000'], $this->countDays('VL', '2026-11-30'));
    }

    public function test_half_a_day_is_half_the_value(): void
    {
        $this->assign($this->fourDay);

        $this->assertSame(['2026-11-03' => '0.625'], $this->countDays('VL', '2026-11-03', coverage: LeaveCoverage::FirstHalf));
    }

    public function test_half_a_day_needs_one_date(): void
    {
        $this->assign($this->regular);

        $this->assertRefused('coverage', 'VL', '2026-11-03', '2026-11-04', LeaveCoverage::SecondHalf);
    }

    public function test_a_date_without_a_schedule_is_refused_by_name(): void
    {
        $this->assertRefused('date_from', 'VL', '2026-11-03', message: 'No schedule on Nov 3, 2026');
    }

    public function test_dates_before_the_start_or_outside_employment_are_refused(): void
    {
        $this->assign($this->regular);
        $this->employee->update(['date_separated' => '2026-11-10']);

        $this->assertRefused('date_from', 'VL', '2026-10-30', message: 'opening balance');
        $this->assertRefused('date_from', 'VL', '2026-11-11', message: 'not employed on Nov 11, 2026');
    }

    public function test_only_rest_days_are_refused(): void
    {
        $this->assign($this->regular);

        $this->assertRefused('date_from', 'VL', '2026-11-07', '2026-11-08', message: 'no work days');
    }

    public function test_spl_and_wellness_are_for_the_current_year_only(): void
    {
        $this->assign($this->regular);

        $this->assertRefused('date_from', 'SPL', '2027-01-04', message: '2026 only');
    }

    public function test_untracked_types_count_calendar_days_without_a_schedule(): void
    {
        $this->assertSame(
            ['2026-11-03' => '1.000', '2026-11-04' => '1.000', '2026-11-05' => '1.000', '2026-11-06' => '1.000', '2026-11-07' => '1.000', '2026-11-08' => '1.000', '2026-11-09' => '1.000'],
            $this->countDays('ML', '2026-11-03', '2026-11-09'),
        );
    }

    public function test_dates_backwards_or_over_a_year_are_refused(): void
    {
        $this->assertRefused('date_to', 'ML', '2026-11-04', '2026-11-03');
        $this->assertRefused('date_to', 'ML', '2026-11-01', '2027-11-02');
    }

    private function assign(Schedule $schedule): void
    {
        ScheduleAssignment::factory()->for($this->employee)->for($schedule)->create(['effective_from' => '2026-11-01']);
    }

    /** @return array<string, string> */
    private function countDays(string $type, string $from, ?string $to = null, LeaveCoverage $coverage = LeaveCoverage::WholeDay): array
    {
        return app(LeaveDayCounter::class)->count(
            $this->employee->fresh(),
            LeaveType::from($type),
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to ?? $from),
            $coverage,
            CarbonImmutable::parse('2026-11-01'),
        );
    }

    private function assertRefused(string $errorKey, string $type, string $from, ?string $to = null, LeaveCoverage $coverage = LeaveCoverage::WholeDay, ?string $message = null): void
    {
        try {
            $this->countDays($type, $from, $to, $coverage);
            $this->fail("{$type} {$from}–{$to} was counted.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());

            if ($message !== null) {
                $this->assertStringContainsString($message, $exception->errors()[$errorKey][0]);
            }
        }
    }
}
