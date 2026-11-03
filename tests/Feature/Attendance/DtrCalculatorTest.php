<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ExceptionType;
use App\Enums\HolidayType;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Models\DayException;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\ComputedDay;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\DtrCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DtrCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // All of October has happened.
        $this->travelTo('2026-11-03 09:00');
    }

    /**
     * Someone on 8–12 and 1–5, Monday to Friday, from October 1.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function regular(array $attributes = []): Employee
    {
        $employee = Employee::factory()->create($attributes);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);

        return $employee;
    }

    /** @param  array<string, array<string, string>>  $days  date => the times printed, by column */
    private function compute(Employee $employee, array $days = []): ComputedDtr
    {
        $upload = DtrUpload::factory()->for($employee)->create();

        foreach ($days as $date => $columns) {
            $upload->days()->create(['date' => $date, ...$columns]);
        }

        return app(DtrCalculator::class)->compute($upload);
    }

    /** @return array{AttendanceStatus, int, int, int, int} */
    private function figures(ComputedDay $day): array
    {
        return [$day->status, $day->late, $day->lateCount, $day->undertime, $day->undertimeCount];
    }

    public function test_late_and_undertime_count_for_each_half_of_the_day(): void
    {
        $day = $this->compute($this->regular(), [
            '2026-10-01' => ['am_arrival' => '08:14', 'am_departure' => '11:45', 'pm_arrival' => '13:09', 'pm_departure' => '16:50'],
        ])->days['2026-10-01'];

        $this->assertSame([AttendanceStatus::Present, 14 + 9, 2, 15 + 10, 2], $this->figures($day));
    }

    public function test_a_missing_tap_costs_nothing_and_is_listed(): void
    {
        $days = $this->compute($this->regular(), [
            '2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_departure' => '17:05'],
            '2026-10-21' => ['am_arrival' => '08:12'],
        ])->days;

        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-02']));
        $this->assertSame(['lunch in'], $days['2026-10-02']->missing);
        // A morning tap alone is still a day at work: late, but not half absent.
        $this->assertSame([AttendanceStatus::Present, 12, 1, 0, 0], $this->figures($days['2026-10-21']));
        $this->assertSame(['lunch out', 'lunch in', 'time out'], $days['2026-10-21']->missing);
        $this->assertSame(0.0, $days['2026-10-21']->absent);
    }

    public function test_a_work_day_without_a_tap_is_absent(): void
    {
        $day = $this->compute($this->regular())->days['2026-10-16'];

        $this->assertSame([AttendanceStatus::Absent, 1.0], [$day->status, $day->absent]);
    }

    public function test_rest_days_holidays_and_days_without_a_schedule_are_not_judged(): void
    {
        Holiday::factory()->create(['date' => '2026-10-08', 'type' => HolidayType::Regular]);
        $employee = Employee::factory()->create();
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-05']);

        $days = $this->compute($employee, ['2026-10-08' => ['am_arrival' => '09:00']])->days;

        $this->assertSame(
            [AttendanceStatus::NoSchedule, AttendanceStatus::Holiday, AttendanceStatus::RestDay],
            [$days['2026-10-02']->status, $days['2026-10-08']->status, $days['2026-10-10']->status],
        );
        $this->assertSame(0, $days['2026-10-08']->late);
    }

    public function test_leave_on_a_work_day_is_not_absent_unless_it_was_cancelled(): void
    {
        $employee = $this->regular();
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-15', 'date_to' => '2026-10-16']);
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'status' => LeaveApplicationStatus::Cancelled]);

        $days = $this->compute($employee)->days;

        $this->assertSame(
            [AttendanceStatus::Leave, AttendanceStatus::Leave, AttendanceStatus::Absent],
            [$days['2026-10-15']->status, $days['2026-10-16']->status, $days['2026-10-20']->status],
        );
    }

    public function test_half_day_leave_leaves_the_other_half_to_the_dtr(): void
    {
        $employee = $this->regular();
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'coverage' => LeaveCoverage::SecondHalf]);
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-14', 'date_to' => '2026-10-14', 'coverage' => LeaveCoverage::FirstHalf]);

        $days = $this->compute($employee, ['2026-10-13' => ['am_arrival' => '08:05', 'am_departure' => '12:00']])->days;

        // The afternoon is on leave: no lunch in or time out to miss.
        $this->assertSame([AttendanceStatus::Present, 5, 1, 0, 0], $this->figures($days['2026-10-13']));
        $this->assertSame([], $days['2026-10-13']->missing);
        $this->assertSame([AttendanceStatus::Absent, 0.5], [$days['2026-10-14']->status, $days['2026-10-14']->absent]);
    }

    public function test_official_business_and_travel_orders_are_not_absent(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-26', 'date_to' => '2026-10-27']);
        DayException::factory()->for($employee)->create(['type' => ExceptionType::TravelOrder, 'date_from' => '2026-10-28', 'date_to' => '2026-10-28']);

        $days = $this->compute($employee)->days;

        $this->assertSame(
            [AttendanceStatus::OfficialBusiness, AttendanceStatus::OfficialBusiness, AttendanceStatus::TravelOrder],
            [$days['2026-10-26']->status, $days['2026-10-27']->status, $days['2026-10-28']->status],
        );
        $this->assertSame(0.0, $days['2026-10-26']->absent);
    }

    public function test_an_afternoon_ob_leaves_the_morning_to_the_dtr_and_is_noted(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'coverage' => LeaveCoverage::SecondHalf]);

        $day = $this->compute($employee, ['2026-10-13' => ['am_arrival' => '08:05', 'am_departure' => '12:00']])->days['2026-10-13'];

        $this->assertSame([AttendanceStatus::Present, 5, 1, 0, 0], $this->figures($day));
        $this->assertSame([[], 'Official business (afternoon)'], [$day->missing, $day->note]);
    }

    public function test_leave_in_the_morning_and_ob_in_the_afternoon_cover_the_day(): void
    {
        $employee = $this->regular();
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-14', 'date_to' => '2026-10-14', 'coverage' => LeaveCoverage::FirstHalf]);
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-14', 'date_to' => '2026-10-14', 'coverage' => LeaveCoverage::SecondHalf]);

        $day = $this->compute($employee)->days['2026-10-14'];

        $this->assertSame([AttendanceStatus::Leave, 0.0], [$day->status, $day->absent]);
    }

    public function test_a_timed_suspension_ends_the_day_early(): void
    {
        Holiday::factory()->create(['date' => '2026-10-14', 'type' => HolidayType::Suspension, 'suspension_starts_at' => '15:00:00']);

        $day = $this->compute($this->regular(), [
            '2026-10-14' => ['am_arrival' => '07:55', 'am_departure' => '12:00', 'pm_arrival' => '12:58', 'pm_departure' => '15:00'],
        ])->days['2026-10-14'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($day));
    }

    public function test_days_after_today_are_not_absent_yet(): void
    {
        $this->travelTo('2026-10-21 18:00');

        $day = $this->compute($this->regular())->days['2026-10-22'];

        $this->assertSame([AttendanceStatus::Upcoming, 0.0], [$day->status, $day->absent]);
    }

    public function test_a_shift_past_midnight_is_not_computed_yet(): void
    {
        $employee = Employee::factory()->create();
        $night = Schedule::factory()->create(['block1_in' => '22:00', 'block1_out' => '02:00', 'block2_in' => '03:00', 'block2_out' => '07:00']);
        ScheduleAssignment::factory()->for($employee)->for($night)->create(['effective_from' => '2026-10-01']);

        $day = $this->compute($employee, ['2026-10-01' => ['am_arrival' => '10:00']])->days['2026-10-01'];

        $this->assertSame([AttendanceStatus::NotComputed, 0], [$day->status, $day->late]);
    }

    public function test_the_month_adds_up_and_converts_to_days_with_the_csc_table(): void
    {
        $dtr = $this->compute($this->regular(), [
            '2026-10-09' => ['am_arrival' => '08:17', 'am_departure' => '12:01', 'pm_arrival' => '12:59', 'pm_departure' => '21:29'],
            '2026-10-21' => ['am_arrival' => '08:12'],
        ]);

        // October 2026 has 22 weekdays; two have taps.
        $this->assertSame([29, 2, 0, 0, 20.0, 1, '0.060'], [
            $dtr->late, $dtr->lateCount, $dtr->undertime, $dtr->undertimeCount, $dtr->absent, $dtr->daysMissingTaps, $dtr->dayEquivalent,
        ]);
    }

    /** @return array<string, array{EmploymentStatus, int}> */
    public static function statuses(): array
    {
        return [
            'permanent' => [EmploymentStatus::Permanent, 17],
            'co-terminous' => [EmploymentStatus::Coterminous, 17],
            'job order' => [EmploymentStatus::JobOrder, 0],
            'contract of service' => [EmploymentStatus::ContractOfService, 0],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_staff_who_earn_leave_credits_are_computed(EmploymentStatus $status, int $late): void
    {
        // Late and undertime come off VL, which job order and contract of service staff do not have.
        $dtr = $this->compute($this->regular(['employment_status' => $status]), [
            '2026-10-09' => ['am_arrival' => '08:17'],
        ]);

        $this->assertSame([$late, $late > 0], [$dtr->late, $dtr->days !== []]);
    }
}
