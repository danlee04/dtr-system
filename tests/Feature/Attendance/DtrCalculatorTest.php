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

    public function test_the_parts_of_a_day_on_an_ob_or_travel_order_take_the_schedules_times(): void
    {
        $employee = $this->regular();
        DayException::factory()->for($employee)->create(['type' => ExceptionType::TravelOrder, 'date_from' => '2026-10-14', 'date_to' => '2026-10-14']);
        DayException::factory()->for($employee)->create(['date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'coverage' => LeaveCoverage::SecondHalf]);
        // Leave and a gate pass are excused too, but the employee was not at work: no times.
        DayException::factory()->for($employee)->create(['type' => ExceptionType::Leave, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15']);

        $days = $this->compute($employee, ['2026-10-13' => ['am_arrival' => '08:05', 'am_departure' => '12:00']])->days;

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
        $this->assertSame([], $days['2026-10-15']->awayTimes);
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
}
