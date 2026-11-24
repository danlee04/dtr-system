<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\DtrSlot;
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
use App\Models\ManualTap;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\ComputedDay;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\DtrCalculator;
use App\Services\Attendance\DtrImporter;
use App\Services\Attendance\PrintedDtr;
use Carbon\CarbonImmutable;
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
        // A morning tap alone is still a day at work: late, and the afternoon with no tap is undertime.
        $this->assertSame([AttendanceStatus::Present, 12, 1, 240, 1], $this->figures($days['2026-10-21']));
        $this->assertSame(['lunch out', 'lunch in', 'time out'], $days['2026-10-21']->missing);
        $this->assertSame(0.0, $days['2026-10-21']->absent);
    }

    public function test_a_half_of_the_day_with_no_tap_is_undertime_for_all_its_hours(): void
    {
        // The four-day week: 8:00–12:00 is 4 hours, 1:00–7:00 PM is 6.
        $employee = Employee::factory()->create();
        $fourDay = Schedule::factory()->create(['work_days' => [1, 2, 3, 4], 'block2_out' => '19:00']);
        ScheduleAssignment::factory()->for($employee)->for($fourDay)->create(['effective_from' => '2026-10-01']);

        $days = $this->compute($employee, [
            '2026-10-05' => ['pm_arrival' => '12:55', 'pm_departure' => '19:02'],
            '2026-10-06' => ['am_arrival' => '07:49'],
        ])->days;

        $this->assertSame([AttendanceStatus::Present, 0, 0, 240, 1], $this->figures($days['2026-10-05']));
        $this->assertSame([AttendanceStatus::Present, 0, 0, 360, 1], $this->figures($days['2026-10-06']));
    }

    public function test_a_work_day_without_a_tap_is_absent_and_undertime_for_all_its_hours(): void
    {
        $day = $this->compute($this->regular())->days['2026-10-16'];

        $this->assertSame([AttendanceStatus::Absent, 0, 0, 480, 1], $this->figures($day));
        $this->assertSame([1.0, 480], [$day->absent, $day->absentMinutes]);

        // The four-day week: 8:00–12:00 and 1:00–7:00 PM, 10 hours.
        $employee = Employee::factory()->create();
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory()->create(['work_days' => [1, 2, 3, 4], 'block2_out' => '19:00', 'leave_days' => '1.250']))->create(['effective_from' => '2026-10-01']);
        $fourDay = $this->compute($employee)->days['2026-10-15'];

        $this->assertSame([600, 600], [$fourDay->undertime, $fourDay->absentMinutes]);

        // 11:00–12:00 and 1:00–7:00 PM is 7 hours: the 22 absent work days of October are 154 hours, 19.250 days by the hours, whatever its leave days say.
        $late = Employee::factory()->create();
        ScheduleAssignment::factory()->for($late)->for(Schedule::factory()->create(['block1_in' => '11:00', 'block1_out' => '12:00', 'block2_in' => '13:00', 'block2_out' => '19:00']))->create(['effective_from' => '2026-10-01']);
        $lateDtr = $this->compute($late);

        $this->assertSame([420, '19.250'], [$lateDtr->days['2026-10-16']->absentMinutes, $lateDtr->absentCharge]);
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

    public function test_leave_on_a_work_day_is_not_absent_unless_it_was_cancelled_or_disapproved(): void
    {
        $employee = $this->regular();
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-15', 'date_to' => '2026-10-16']);
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'status' => LeaveApplicationStatus::Cancelled]);
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-21', 'date_to' => '2026-10-21', 'status' => LeaveApplicationStatus::Disapproved]);

        $days = $this->compute($employee)->days;

        $this->assertSame(
            [AttendanceStatus::Leave, AttendanceStatus::Leave, AttendanceStatus::Absent, AttendanceStatus::Absent],
            [$days['2026-10-15']->status, $days['2026-10-16']->status, $days['2026-10-20']->status, $days['2026-10-21']->status],
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
        // The morning is on leave and the afternoon has no tap: half absent, 4 hours undertime, which comes off SL first at Final.
        $this->assertSame([AttendanceStatus::Absent, 0.5, 240, 240], [$days['2026-10-14']->status, $days['2026-10-14']->absent, $days['2026-10-14']->undertime, $days['2026-10-14']->absentMinutes]);
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

    public function test_the_parts_of_a_day_on_an_ob_travel_order_or_gate_pass_take_the_schedules_times(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['type' => ExceptionType::TravelOrder, 'date_from' => '2026-10-14', 'date_to' => '2026-10-14']);
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'coverage' => LeaveCoverage::SecondHalf]);
        // A gate pass shows them too (HR, 2026-11-23).
        DayException::factory()->for($employee)->create(['type' => ExceptionType::GatePass, 'date_from' => '2026-10-16', 'date_to' => '2026-10-16', 'coverage' => LeaveCoverage::SecondHalf]);
        // Leave is excused too, but the employee was not at work: no times.
        DayException::factory()->for($employee)->create(['type' => ExceptionType::Leave, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15']);

        $days = $this->compute($employee, [
            '2026-10-13' => ['am_arrival' => '08:05', 'am_departure' => '12:00'],
            '2026-10-16' => ['am_arrival' => '07:55', 'am_departure' => '12:00'],
        ])->days;

        $away = fn (string $date): array => array_map(fn (array $slot): array => [$slot['time'], $slot['status']], $days[$date]->awayTimes);
        $this->assertSame([
            'am_arrival' => ['08:00', AttendanceStatus::TravelOrder],
            'am_departure' => ['12:00', AttendanceStatus::TravelOrder],
            'pm_arrival' => ['13:00', AttendanceStatus::TravelOrder],
            'pm_departure' => ['17:00', AttendanceStatus::TravelOrder],
        ], $away('2026-10-14'));
        // Only the afternoon is on the OB; the morning's taps are the DTR's own.
        $this->assertSame([
            'pm_arrival' => ['13:00', AttendanceStatus::OfficialBusiness],
            'pm_departure' => ['17:00', AttendanceStatus::OfficialBusiness],
        ], $away('2026-10-13'));
        $this->assertSame([
            'pm_arrival' => ['13:00', AttendanceStatus::GatePass],
            'pm_departure' => ['17:00', AttendanceStatus::GatePass],
        ], $away('2026-10-16'));
        $this->assertSame([], $days['2026-10-15']->awayTimes);
    }

    public function test_a_day_carries_the_schedules_time_for_each_slot_and_the_flexi_time_out_due(): void
    {
        $regular = $this->compute($this->regular(), ['2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58']])->days['2026-10-02'];
        $flexi = $this->compute($this->regular(['flexitime' => true]), ['2026-10-02' => ['am_arrival' => '09:00', 'am_departure' => '12:05', 'pm_arrival' => '12:58']])->days['2026-10-02'];

        $this->assertSame(['am_arrival' => '08:00', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00'], $regular->scheduleTimes);
        // In at 9:00 on flexitime, 6:00 is due.
        $this->assertSame('18:00', $flexi->scheduleTimes['pm_departure']);
    }

    public function test_leave_or_a_gate_pass_hr_marks_covers_its_part_of_the_day_like_an_ob(): void
    {
        $employee = $this->regular();
        // HR marks leave by hand while the leave module is not yet in use: no application, no credits charged (HR, 2026-11-19).
        DayException::factory()->for($employee)->create(['type' => ExceptionType::Leave, 'date_from' => '2026-10-16', 'date_to' => '2026-10-16']);
        DayException::factory()->for($employee)->create(['type' => ExceptionType::GatePass, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'coverage' => LeaveCoverage::SecondHalf]);

        $days = $this->compute($employee, ['2026-10-15' => ['am_arrival' => '07:55', 'am_departure' => '12:00']])->days;

        $this->assertSame([AttendanceStatus::Leave, 0, 0, 0, 0], $this->figures($days['2026-10-16']));
        $this->assertSame(0.0, $days['2026-10-16']->absent);
        // The afternoon on a gate pass is neither missing nor undertime.
        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-15']));
        $this->assertSame([[], 'Gate pass (afternoon)'], [$days['2026-10-15']->missing, $days['2026-10-15']->note]);
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

    public function test_a_shift_past_midnight_with_a_break_is_not_computed_yet(): void
    {
        $employee = Employee::factory()->create();
        $night = Schedule::factory()->create(['block1_in' => '22:00', 'block1_out' => '02:00', 'block2_in' => '03:00', 'block2_out' => '07:00']);
        ScheduleAssignment::factory()->for($employee)->for($night)->create(['effective_from' => '2026-10-01']);

        $day = $this->compute($employee, ['2026-10-01' => ['am_arrival' => '10:00']])->days['2026-10-01'];

        $this->assertSame([AttendanceStatus::NotComputed, 0], [$day->status, $day->late]);
    }

    /**
     * A doctor on the duty roster: 24H (8:00 AM to 8:00 AM the next day, no break),
     * the 8 AM to 4 PM duty some have after it, or a day off (HR, 2026-11-24).
     *
     * @param  array<string, ?string>  $roster  date => schedule code, or null for OFF
     */
    private function doctor(array $roster): Employee
    {
        $employee = Employee::factory()->create();
        $schedules = [
            '24H' => Schedule::factory()->rosterOnly()->create(['code' => '24H', 'block1_in' => '08:00', 'block1_out' => '08:00', 'block2_in' => null, 'block2_out' => null]),
            '8-4' => Schedule::factory()->rosterOnly()->create(['code' => '8-4', 'block1_in' => '08:00', 'block1_out' => '16:00', 'block2_in' => null, 'block2_out' => null]),
        ];

        foreach ($roster as $date => $code) {
            RosterEntry::factory()->for($employee)->create(['date' => $date, 'schedule_id' => $code === null ? null : $schedules[$code]->id]);
        }

        return $employee;
    }

    public function test_a_24_hour_duty_ends_on_the_next_days_morning_departure(): void
    {
        // As Ybiernas, Oct 3: in 8:19 AM, out the next day 8:35 AM, printed on Oct 4's row; Oct 4 off.
        $days = $this->compute($this->doctor(['2026-10-03' => '24H', '2026-10-04' => null]), [
            '2026-10-03' => ['am_arrival' => '08:19'],
            '2026-10-04' => ['am_departure' => '08:35'],
        ])->days;

        $this->assertSame([AttendanceStatus::Present, 19, 1, 0, 0], $this->figures($days['2026-10-03']));
        $this->assertSame([], $days['2026-10-03']->missing);
        $this->assertSame(AttendanceStatus::RestDay, $days['2026-10-04']->status);
    }

    public function test_leaving_a_24_hour_duty_before_8_is_undertime(): void
    {
        $day = $this->compute($this->doctor(['2026-10-09' => '24H', '2026-10-10' => null]), [
            '2026-10-09' => ['am_arrival' => '07:58'],
            '2026-10-10' => ['am_departure' => '07:30'],
        ])->days['2026-10-09'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 30, 1], $this->figures($day));
    }

    public function test_a_24_hour_duty_runs_on_into_the_next_mornings_duty_with_no_tap_at_8(): void
    {
        // As Ybiernas, Oct 6 to 7: in 7:54 AM, printed in the O.T. column; no tap at 8:00 AM; out 4:21 PM from the 8-4 duty.
        $days = $this->compute($this->doctor(['2026-10-06' => '24H', '2026-10-07' => '8-4']), [
            '2026-10-06' => ['ot_arrival' => '07:54'],
            '2026-10-07' => ['pm_departure' => '16:21'],
        ])->days;

        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-06']));
        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-07']));
        $this->assertSame([[], []], [$days['2026-10-06']->missing, $days['2026-10-07']->missing]);
    }

    public function test_a_24_hour_duty_with_no_tap_is_three_days_absent(): void
    {
        // All 24 hours (HR, 2026-11-24).
        $day = $this->compute($this->doctor(['2026-10-12' => '24H', '2026-10-13' => null]))->days['2026-10-12'];

        $this->assertSame([AttendanceStatus::Absent, 24 * 60, 3.0], [$day->status, $day->undertime, $day->absent]);
    }

    public function test_the_month_adds_up_and_converts_to_days_with_the_csc_table(): void
    {
        $dtr = $this->compute($this->regular(), [
            '2026-10-09' => ['am_arrival' => '08:17', 'am_departure' => '12:01', 'pm_arrival' => '12:59', 'pm_departure' => '21:29'],
            '2026-10-21' => ['am_arrival' => '08:12'],
        ]);

        // October 2026 has 22 weekdays; two have taps. The 21st's afternoon has none: 4 hours undertime.
        // The 20 absent days are 8 hours undertime each, 20.000 of the 20.560 days, and that part comes off SL first.
        $this->assertSame([29, 2, 240 + 20 * 480, 21, 20.0, 1, '20.560', '20.000'], [
            $dtr->late, $dtr->lateCount, $dtr->undertime, $dtr->undertimeCount, $dtr->absent, $dtr->daysMissingTaps, $dtr->dayEquivalent, $dtr->absentCharge,
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

    /** HR copies in a time the employee wrote on the DTR and the supervisor signed. */
    private function hrFills(Employee $employee, string $date, DtrSlot $slot, string $time): void
    {
        ManualTap::factory()->for($employee)->create(['date' => $date, 'slot' => $slot, 'time' => $time]);
    }

    public function test_a_time_hr_filled_counts_like_a_printed_tap(): void
    {
        $employee = $this->regular();
        $this->hrFills($employee, '2026-10-02', DtrSlot::PmDeparture, '16:30');

        $day = $this->compute($employee, [
            '2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58'],
        ])->days['2026-10-02'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 30, 1], $this->figures($day));
        $this->assertSame([], $day->missing);
    }

    public function test_times_hr_filled_on_a_day_without_a_tap_make_it_a_day_at_work(): void
    {
        $employee = $this->regular();
        $this->hrFills($employee, '2026-10-16', DtrSlot::AmArrival, '08:05');
        $this->hrFills($employee, '2026-10-16', DtrSlot::PmDeparture, '17:00');

        $day = $this->compute($employee)->days['2026-10-16'];

        $this->assertSame([AttendanceStatus::Present, 5, 1, 0, 0], $this->figures($day));
        $this->assertSame([0.0, ['lunch out', 'lunch in']], [$day->absent, $day->missing]);
    }

    public function test_a_time_hr_corrected_wins_over_the_one_printed(): void
    {
        $employee = $this->regular();
        // The device printed 12:15 PM for a time in of 8:00 AM, signed on the paper.
        $this->hrFills($employee, '2026-10-14', DtrSlot::AmArrival, '08:00');

        $day = $this->compute($employee, [
            '2026-10-14' => ['am_arrival' => '12:15', 'am_departure' => '12:29', 'pm_arrival' => '12:49', 'pm_departure' => '17:01'],
        ])->days['2026-10-14'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($day));
    }

    public function test_times_hr_filled_still_count_after_the_month_is_uploaded_again(): void
    {
        $employee = $this->regular();
        $this->compute($employee, ['2026-10-02' => ['am_arrival' => '07:47']]);
        $this->hrFills($employee, '2026-10-02', DtrSlot::PmDeparture, '16:30');

        // IT reprints the month: HR deletes the old upload, since an upload never replaces one, then uploads the reprint.
        DtrUpload::query()->sole()->delete();
        $reprint = app(DtrImporter::class)->import(new PrintedDtr(
            $employee->full_name,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
            ['2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58']],
        ), 'reprint.pdf', null);

        $this->assertSame(1, DtrUpload::query()->count());
        $this->assertSame(30, app(DtrCalculator::class)->compute($reprint)->days['2026-10-02']->undertime);
    }

    public function test_hr_may_fill_the_missing_taps_and_every_slot_of_a_half_with_none(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'coverage' => LeaveCoverage::SecondHalf]);

        $days = $this->compute($employee, [
            '2026-10-01' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58', 'pm_departure' => '17:05'],
            '2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_departure' => '17:05'],
        ])->days;

        $this->assertSame([], $days['2026-10-01']->fillable);
        $this->assertSame([DtrSlot::PmArrival], $days['2026-10-02']->fillable);
        $this->assertSame(DtrSlot::cases(), $days['2026-10-16']->fillable);
        // The afternoon is on an OB, so only the morning is left to fill.
        $this->assertSame([DtrSlot::AmArrival, DtrSlot::AmDeparture], $days['2026-10-20']->fillable);
        $this->assertSame([], $days['2026-10-10']->fillable);
    }

    public function test_the_part_of_a_day_with_no_tap_is_what_an_ob_could_cover(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'coverage' => LeaveCoverage::SecondHalf]);

        $days = $this->compute($employee, [
            '2026-10-01' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58', 'pm_departure' => '17:05'],
            '2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05'],
            '2026-10-05' => ['pm_arrival' => '12:58', 'pm_departure' => '17:05'],
            '2026-10-06' => ['am_arrival' => '07:47', 'pm_departure' => '17:05'],
        ])->days;

        $this->assertSame(
            [null, LeaveCoverage::SecondHalf, LeaveCoverage::FirstHalf, null, LeaveCoverage::WholeDay, LeaveCoverage::FirstHalf, null],
            [
                $days['2026-10-01']->untappedCoverage(),
                $days['2026-10-02']->untappedCoverage(),
                $days['2026-10-05']->untappedCoverage(),
                // A tap in each half: only taps are missing, not a half.
                $days['2026-10-06']->untappedCoverage(),
                $days['2026-10-16']->untappedCoverage(),
                // The afternoon is on an OB already.
                $days['2026-10-20']->untappedCoverage(),
                $days['2026-10-10']->untappedCoverage(),
            ],
        );
    }

    public function test_on_a_one_block_day_time_in_is_the_am_arrival_and_time_out_the_pm_departure(): void
    {
        $employee = Employee::factory()->create();
        $straight = Schedule::factory()->create(['block1_in' => '07:00', 'block1_out' => '15:00', 'block2_in' => null, 'block2_out' => null]);
        ScheduleAssignment::factory()->for($employee)->for($straight)->create(['effective_from' => '2026-10-01']);
        $this->hrFills($employee, '2026-10-02', DtrSlot::PmDeparture, '14:45');

        $days = $this->compute($employee, ['2026-10-02' => ['am_arrival' => '06:55'], '2026-10-05' => ['pm_departure' => '15:00']])->days;

        $this->assertSame([AttendanceStatus::Present, 0, 0, 15, 1], $this->figures($days['2026-10-02']));
        $this->assertSame([DtrSlot::AmArrival], $days['2026-10-05']->fillable);
        $this->assertSame([DtrSlot::AmArrival, DtrSlot::PmDeparture], $days['2026-10-06']->fillable);
    }

    /** A day's four taps, lunch at 12:00 and 1:00 unless given. */
    private static function taps(?string $in, ?string $out, string $lunchOut = '12:00', string $lunchIn = '13:00'): array
    {
        return ['am_arrival' => $in, 'am_departure' => $lunchOut, 'pm_arrival' => $lunchIn, 'pm_departure' => $out];
    }

    public function test_flexi_counts_eight_hours_from_a_time_in_between_seven_and_nine(): void
    {
        $days = $this->compute($this->regular(['flexitime' => true]), [
            '2026-10-09' => self::taps('06:30', '15:30'),
            '2026-10-06' => self::taps('07:15', '16:15'),
            '2026-10-07' => self::taps('08:30', '17:30'),
            '2026-10-08' => self::taps('09:10', '18:10'),
        ])->days;

        // In before 7:00 counts as 7:00, so 4:00 is due; after 9:00 is late, and 6:00 is the latest due.
        $this->assertSame([AttendanceStatus::Present, 0, 0, 30, 1], $this->figures($days['2026-10-09']));
        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-06']));
        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($days['2026-10-07']));
        $this->assertSame([AttendanceStatus::Present, 10, 1, 0, 0], $this->figures($days['2026-10-08']));
    }

    public function test_flexi_with_the_afternoon_suspended_is_late_after_eight(): void
    {
        Holiday::factory()->create(['date' => '2026-10-15', 'type' => HolidayType::Suspension, 'suspension_starts_at' => '13:00:00']);

        // Work ends at 1:00, so the day is the morning only: in at 9:00 is 60 minutes late (HR, 2026-11-24).
        $day = $this->compute($this->regular(['flexitime' => true]), [
            '2026-10-15' => ['am_arrival' => '09:00', 'am_departure' => '12:00'],
        ])->days['2026-10-15'];

        $this->assertSame([60, 0], [$day->late, $day->undertime]);
    }

    public function test_flexi_does_not_apply_on_a_monday(): void
    {
        // Not granted on Mondays (the office's policy, 2026-11-23): in at 8:20 is 20 minutes late, and 5:00 is due.
        $days = $this->compute($this->regular(['flexitime' => true]), [
            '2026-10-19' => self::taps('08:20', '17:20'),
            '2026-10-26' => self::taps('07:30', '16:30'),
        ])->days;

        $this->assertSame([20, 0], [$days['2026-10-19']->late, $days['2026-10-19']->undertime]);
        $this->assertSame([0, 30], [$days['2026-10-26']->late, $days['2026-10-26']->undertime]);
    }

    public function test_flexi_keeps_the_break_at_twelve_to_one(): void
    {
        $day = $this->compute($this->regular(['flexitime' => true]), ['2026-10-09' => self::taps('08:00', '17:00', lunchIn: '13:10')])->days['2026-10-09'];

        $this->assertSame([AttendanceStatus::Present, 10, 1, 0, 0], $this->figures($day));
    }

    public function test_flexi_with_no_time_in_owes_five_oclock(): void
    {
        $day = $this->compute($this->regular(['flexitime' => true]), ['2026-10-12' => self::taps(null, '16:30')])->days['2026-10-12'];

        // 30 minutes short of 5:00, and the morning with no time in until HR fills it.
        $this->assertSame(240 + 30, $day->undertime);
        $this->assertContains('time in', $day->missing);
    }

    public function test_flexi_still_counts_an_untapped_afternoon_as_four_hours(): void
    {
        $day = $this->compute($this->regular(['flexitime' => true]), [
            '2026-10-13' => ['am_arrival' => '07:00', 'am_departure' => '12:00'],
        ])->days['2026-10-13'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 240, 1], $this->figures($day));
    }

    public function test_a_suspension_cuts_the_flexi_time_out_due(): void
    {
        Holiday::factory()->create(['date' => '2026-10-14', 'type' => HolidayType::Suspension, 'suspension_starts_at' => '15:00:00']);

        // In at 9:00 would owe 6:00, but work ended at the suspension's 3:00.
        $day = $this->compute($this->regular(['flexitime' => true]), ['2026-10-14' => self::taps('09:00', '15:00')])->days['2026-10-14'];

        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($day));
    }

    public function test_flexi_does_not_apply_unchecked_or_on_another_schedule(): void
    {
        $unchecked = $this->compute($this->regular(), ['2026-10-07' => self::taps('08:30', '17:30')])->days['2026-10-07'];

        $fourDay = Employee::factory()->create(['flexitime' => true]);
        ScheduleAssignment::factory()->for($fourDay)->for(Schedule::factory()->create(['block2_out' => '19:00']))->create(['effective_from' => '2026-10-01']);
        $otherSchedule = $this->compute($fourDay, ['2026-10-07' => self::taps('08:30', '19:00')])->days['2026-10-07'];

        $this->assertSame([30, 30], [$unchecked->late, $otherSchedule->late]);
    }

    public function test_flexi_with_the_morning_on_an_ob_owes_five_oclock_whatever_it_tapped(): void
    {
        $employee = $this->regular(['flexitime' => true]);
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'coverage' => LeaveCoverage::FirstHalf]);

        // A tap at 9:00 on the OB morning is not the time in: the morning counts as 8:00, so 5:00 is due, not 6:00.
        $day = $this->compute($employee, ['2026-10-15' => self::taps('09:00', '17:00')])->days['2026-10-15'];

        $this->assertSame(0, $day->undertime);
    }

    public function test_flexi_with_the_afternoon_on_leave_or_an_ob_is_late_after_eight(): void
    {
        $employee = $this->regular(['flexitime' => true]);
        LeaveApplication::factory()->for($employee)->create(['date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'coverage' => LeaveCoverage::SecondHalf]);
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-16', 'date_to' => '2026-10-16', 'coverage' => LeaveCoverage::SecondHalf]);

        // In at 9:00 and out at 12:00 on a morning-only day: 60 minutes late, as on 8–5 (HR, 2026-11-23).
        $days = $this->compute($employee, [
            '2026-10-15' => ['am_arrival' => '09:00', 'am_departure' => '12:00'],
            '2026-10-16' => ['am_arrival' => '09:00', 'am_departure' => '12:00'],
        ])->days;

        $this->assertSame([60, 0], [$days['2026-10-15']->late, $days['2026-10-15']->undertime]);
        $this->assertSame([60, 0], [$days['2026-10-16']->late, $days['2026-10-16']->undertime]);
    }

    public function test_a_missing_time_in_is_undertime_for_the_morning_until_hr_fills_it(): void
    {
        $employee = $this->regular();
        $taps = ['2026-10-05' => self::taps(null, '18:10', '12:01', '12:38')];

        // Not proof of a late arrival, so not counted as one; but it costs the morning until the signed DTR fills it (HR, 2026-11-20).
        $day = $this->compute($employee, $taps)->days['2026-10-05'];
        $this->assertSame([AttendanceStatus::Present, 0, 0, 240, 1], $this->figures($day));
        $this->assertContains('time in', $day->missing);

        $this->hrFills($employee, '2026-10-05', DtrSlot::AmArrival, '08:00');
        $this->assertSame([AttendanceStatus::Present, 0, 0, 0, 0], $this->figures($this->compute($employee, $taps)->days['2026-10-05']));
    }

    public function test_other_missing_taps_still_cost_nothing(): void
    {
        // In at 8:00 and out at 5:00 with no lunch taps, and an afternoon with no time out: listed, not charged.
        $days = $this->compute($this->regular(), [
            '2026-10-06' => ['am_arrival' => '08:00', 'pm_departure' => '17:00'],
            '2026-10-07' => self::taps('08:00', null),
        ])->days;

        $this->assertSame([0, 0], [$days['2026-10-06']->undertime, $days['2026-10-07']->undertime]);
    }
}
