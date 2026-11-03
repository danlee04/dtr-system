<?php

namespace Tests\Feature;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\DayException;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\DashboardFigures;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFiguresTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    private DashboardFigures $figures;

    protected function setUp(): void
    {
        parent::setUp();

        // Tuesday, November 3, 2026: all of October has happened.
        $this->travelTo('2026-11-03 09:00');
        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
        $this->figures = app(DashboardFigures::class);
    }

    /**
     * Someone on 8–12 and 1–5, Monday to Friday, since September.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function staff(array $attributes = [], ?Office $office = null): Employee
    {
        $employee = Employee::factory()->for($office ?? $this->nursing)->create(['middle_name' => null, ...$attributes]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-09-01']);

        return $employee;
    }

    /** A DTR of one day, tapped in full. */
    private function day(Employee $employee, string $date, string $in = '08:00', string $out = '17:00'): void
    {
        DtrUpload::factory()->for($employee)->create(['period_start' => $date, 'period_end' => $date])
            ->days()->create(['date' => $date, 'am_arrival' => $in, 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => $out]);
    }

    private function opening(Employee $employee): void
    {
        LeaveLedgerEntry::factory()->for($employee)->create(['kind' => LedgerKind::Opening, 'posting_key' => "opening:{$employee->id}:VL"]);
    }

    public function test_last_month_adds_up_by_weekday_with_the_share_of_days_begun_on_time(): void
    {
        $ana = $this->staff();
        $ben = $this->staff();
        $this->staff();
        $this->day($ana, '2026-10-01', in: '08:10');
        $this->day($ana, '2026-10-02', out: '16:50');
        $this->day($ben, '2026-10-05', in: '08:30');

        $month = $this->figures->month(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame([2, 3, 40, 10, 0.0], [$month['dtrs'], $month['staff'], $month['late'], $month['undertime'], $month['absent']]);
        // Each employee's month is converted, then added: 20 minutes is 0.042 and 30 is 0.062.
        $this->assertSame('0.104', $month['dayEquivalent']);
        // October 5 is a Monday, the 1st a Thursday and the 2nd a Friday.
        $this->assertSame(['late' => 30, 'undertime' => 0], $month['byWeekday'][1]);
        $this->assertSame(['late' => 10, 'undertime' => 0], $month['byWeekday'][4]);
        $this->assertSame(['late' => 0, 'undertime' => 10], $month['byWeekday'][5]);
        $this->assertSame(['late' => 0, 'undertime' => 0], $month['byWeekday'][7]);
        // Of three days at work, one began on time.
        $this->assertSame(33, $month['onTimeRate']);
    }

    public function test_the_offices_furthest_behind_on_uploads_come_first(): void
    {
        $pharmacy = Office::factory()->create(['name' => 'Pharmacy']);
        $records = Office::factory()->create(['name' => 'Records']);
        $this->day($this->staff(), '2026-10-01');
        $this->staff();
        $this->staff();
        $this->staff(office: $pharmacy);
        $this->day($this->staff(office: $records), '2026-10-01');

        $month = $this->figures->month(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame([
            ['office' => 'Nursing Service', 'uploaded' => 1, 'staff' => 3],
            ['office' => 'Pharmacy', 'uploaded' => 0, 'staff' => 1],
        ], $month['officesBehind']);
    }

    public function test_whoever_is_on_leave_ob_or_travel_today_is_out(): void
    {
        $ana = $this->staff(['last_name' => 'Abad']);
        $ben = $this->staff(['last_name' => 'Bautista']);
        $carl = $this->staff(['last_name' => 'Cruz']);
        $dina = $this->staff(['last_name' => 'Diaz']);
        $ed = $this->staff(['last_name' => 'Estrada']);
        LeaveApplication::factory()->for($ana)->create(['date_from' => '2026-11-02', 'date_to' => '2026-11-04', 'status' => LeaveApplicationStatus::Approved]);
        LeaveApplication::factory()->for($ben)->create(['leave_type' => LeaveType::Sick]);
        LeaveApplication::factory()->for($carl)->create(['status' => LeaveApplicationStatus::Cancelled]);
        DayException::factory()->for($dina)->create(['date_from' => '2026-11-03', 'date_to' => '2026-11-03']);
        LeaveApplication::factory()->for($ed)->create(['date_from' => '2026-11-04', 'date_to' => '2026-11-04']);

        $out = array_map(
            fn (array $row): array => [$row['employee']->last_name, $row['reason']],
            $this->figures->outToday(CarbonImmutable::today()),
        );

        $this->assertSame([['Abad', 'Vacation Leave'], ['Bautista', 'Sick Leave'], ['Diaz', 'Official business']], $out);
    }

    public function test_this_years_leave_by_type(): void
    {
        $ana = $this->staff();

        foreach ([['VL', '3.000'], ['FL', '2.000'], ['SL', '3.000'], ['SPL', '1.000'], ['SOLO', '1.000']] as [$type, $days]) {
            LeaveApplication::factory()->for($ana)->create(['leave_type' => $type, 'days' => $days, 'date_from' => '2026-03-02', 'date_to' => '2026-03-02']);
        }

        LeaveApplication::factory()->for($ana)->create(['days' => '4.000', 'status' => LeaveApplicationStatus::Cancelled]);
        LeaveApplication::factory()->for($ana)->create(['days' => '5.000', 'date_from' => '2025-12-01', 'date_to' => '2025-12-05']);

        // Forced leave is vacation leave; Solo Parent goes with the rarer types.
        $this->assertSame([
            'total' => '10.000',
            'types' => [
                ['label' => 'Vacation', 'days' => '5.000', 'percent' => 50],
                ['label' => 'Sick', 'days' => '3.000', 'percent' => 30],
                ['label' => 'Special privilege', 'days' => '1.000', 'percent' => 10],
                ['label' => 'Others', 'days' => '1.000', 'percent' => 10],
            ],
        ], $this->figures->leaveByType(2026));
    }

    public function test_the_next_step_is_the_opening_balances_then_last_months_dtrs(): void
    {
        config(['leave.start_date' => '2026-12-01']);
        $ana = $this->staff();
        $ben = $this->staff();
        $this->opening($ana);

        $this->assertSame(['step' => 'opening-balances', 'done' => 1, 'of' => 2], $this->figures->nextStep(CarbonImmutable::today(), ['dtrs' => 0, 'staff' => 2]));

        $this->opening($ben);

        $this->assertSame(['step' => 'uploads', 'done' => 1, 'of' => 2], $this->figures->nextStep(CarbonImmutable::today(), ['dtrs' => 1, 'staff' => 2]));
        $this->assertSame(['step' => 'caught-up', 'done' => 2, 'of' => 2], $this->figures->nextStep(CarbonImmutable::today(), ['dtrs' => 2, 'staff' => 2]));
    }

    public function test_once_the_system_has_taken_over_the_opening_balances_are_not_the_next_step(): void
    {
        config(['leave.start_date' => '2026-11-01']);
        $this->staff();

        $this->assertSame('uploads', $this->figures->nextStep(CarbonImmutable::today(), ['dtrs' => 0, 'staff' => 1])['step']);
    }
}
