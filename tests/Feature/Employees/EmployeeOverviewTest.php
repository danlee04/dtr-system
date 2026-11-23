<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LedgerKind;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmployeeOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 09:00');
        config(['leave.start_date' => '2026-12-01']);
        $this->actingAs(User::factory()->create());
    }

    /** On REG, 8 to 12 and 1 to 5, since January. */
    private function scheduled(Employee $employee): Employee
    {
        $regular = Schedule::query()->firstWhere('code', 'REG') ?? Schedule::factory()->create(['code' => 'REG']);
        ScheduleAssignment::factory()->for($employee)->for($regular)->create(['effective_from' => '2026-01-01']);

        return $employee;
    }

    /** Nothing for HR to look at: a schedule, an opening balance, nothing waiting. */
    private function settled(array $attributes = []): Employee
    {
        $employee = $this->scheduled(Employee::factory()->create($attributes));
        LeaveLedgerEntry::factory()->for($employee)->create(['kind' => LedgerKind::Opening, 'days' => '12.500', 'posted_on' => '2026-11-30']);

        return $employee;
    }

    /** A DTR for the month whose first $count weekdays are five minutes late. */
    private function lates(Employee $employee, string $month, int $count): void
    {
        $first = CarbonImmutable::parse("{$month}-01");
        $upload = DtrUpload::factory()->for($employee)->create(['period_start' => $first, 'period_end' => $first->endOfMonth()]);

        foreach (CarbonPeriod::create($first, $first->endOfMonth()) as $date) {
            if ($date->isWeekday()) {
                $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => $count-- > 0 ? '08:05' : '07:55', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
            }
        }
    }

    private function page(Employee $employee): TestResponse
    {
        return $this->get(EmployeeResource::getUrl('view', ['record' => $employee]))->assertOk();
    }

    public function test_the_overview_shows_last_months_lates_the_balances_and_the_details(): void
    {
        $employee = $this->settled(['date_hired' => '2022-03-14']);
        $this->lates($employee, '2026-10', 3);

        $this->page($employee)->assertSeeInOrder([
            'October', '3 lates', '15 min late',
            'Vacation', '12.500', 'Sick', '0.000', 'Special privilege', '0.000', 'Wellness', '0.000',
            // The schedule left the cards for the Schedules tab (HR's reference, 2026-11-23).
            'Job Details', $employee->employee_number, 'Mar 14, 2022 · 4 years 7 months',
        ]);
    }

    public function test_staff_who_earn_no_leave_have_no_cards(): void
    {
        $employee = $this->settled(['employment_status' => EmploymentStatus::JobOrder]);
        $this->lates($employee, '2026-10', 3);

        $this->page($employee)
            ->assertDontSee('class="dtr-emp-cards"', escape: false)
            ->assertDontSee('days left')
            ->assertDontSee('3 lates');
    }

    public function test_a_month_without_a_dtr_says_so(): void
    {
        $this->page($this->settled())->assertSeeInOrder(['October', 'No DTR']);
    }

    public function test_nothing_needs_a_look_when_all_is_in_order(): void
    {
        $this->page($this->settled())->assertDontSee('Needs a look');
    }

    public function test_a_missing_schedule_comes_first(): void
    {
        $employee = Employee::factory()->create();
        LeaveApplication::factory()->for($employee)->create();

        $this->page($employee)
            ->assertSee(['Needs a look', 'No schedule yet'])
            ->assertDontSee('No opening balances yet')
            ->assertDontSee('leave application waiting');
    }

    public function test_opening_balances_are_asked_of_those_hired_before_go_live(): void
    {
        $this->travelTo('2027-01-10 09:00');

        $this->page($this->scheduled(Employee::factory()->create(['date_hired' => '2020-06-01'])))
            ->assertSee('No opening balances yet');

        $this->page($this->scheduled(Employee::factory()->create(['date_hired' => '2026-12-14'])))
            ->assertDontSee('Needs a look');
    }

    public function test_leave_waiting_for_approval_needs_a_look(): void
    {
        $employee = $this->settled();
        LeaveApplication::factory()->for($employee)->create();
        LeaveApplication::factory()->for($employee)->create(['status' => LeaveApplicationStatus::Approved]);

        $this->page($employee)->assertSee('1 leave application waiting');
    }

    public function test_ten_lates_last_month_need_a_look(): void
    {
        $employee = $this->settled();
        $this->lates($employee, '2026-10', 10);

        $this->page($employee)->assertSee('Late 10 times in October');
    }

    public function test_the_deductions_card_lists_what_still_stands_this_year_in_place_of_the_name(): void
    {
        $employee = $this->settled();
        $october = DtrUpload::factory()->for($employee)->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
        $deduct = fn (string $days, LedgerKind $kind, string $remarks, string $postedOn) => LeaveLedgerEntry::factory()->for($employee)->create([
            'leave_type' => 'VL', 'days' => $days, 'kind' => $kind, 'remarks' => $remarks, 'posted_on' => $postedOn, 'period' => '2026-10', 'dtr_upload_id' => $october->id,
        ]);
        // Marked final, reopened, then final again: only the last stands.
        $deduct('-0.077', LedgerKind::DtrDeduction, 'October 2026 DTR: 37 min late and undertime', '2026-11-02');
        $deduct('0.077', LedgerKind::DtrReopened, 'October 2026 DTR reopened', '2026-11-03');
        $deduct('-0.119', LedgerKind::DtrDeduction, 'October 2026 DTR: 57 min late and undertime', '2026-11-03');
        // A leave taken, and one cancelled, which takes nothing.
        $taken = LeaveApplication::factory()->for($employee)->create(['leave_type' => 'SL', 'date_from' => '2026-10-07', 'date_to' => '2026-10-07', 'status' => LeaveApplicationStatus::Approved]);
        LeaveLedgerEntry::factory()->for($employee)->create(['leave_type' => 'SL', 'days' => '-1.000', 'kind' => LedgerKind::Application, 'leave_application_id' => $taken->id, 'posted_on' => '2026-10-06']);
        $cancelled = LeaveApplication::factory()->for($employee)->create(['leave_type' => 'VL', 'date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'status' => LeaveApplicationStatus::Cancelled]);
        LeaveLedgerEntry::factory()->for($employee)->create(['leave_type' => 'VL', 'days' => '-1.000', 'kind' => LedgerKind::Application, 'leave_application_id' => $cancelled->id, 'posted_on' => '2026-10-19']);
        LeaveLedgerEntry::factory()->for($employee)->create(['leave_type' => 'VL', 'days' => '1.000', 'kind' => LedgerKind::Cancellation, 'leave_application_id' => $cancelled->id, 'posted_on' => '2026-10-19']);

        // HR, 2026-11-23: the deductions, newest first, net of reopens and cancellations, with a total per leave.
        $this->page($employee)
            ->assertDontSee('DTR uploads are matched to this name.')
            ->assertSeeInOrder(['Deductions', 'October 2026 DTR', '57 min late and undertime', 'VL', '−0.119', 'Sick Leave', 'Oct 7, 2026', 'SL', '−1.000', 'Total', 'VL −0.119', 'SL −1.000'])
            ->assertDontSee('37 min late and undertime')
            ->assertDontSee('Oct 20, 2026');
    }

    public function test_an_employee_with_no_deductions_this_year_is_told_so(): void
    {
        $this->page($this->settled())->assertSeeInOrder(['Deductions', 'No deductions in 2026.']);
    }

    public function test_only_active_employees_need_a_look(): void
    {
        $this->page(Employee::factory()->create(['date_separated' => '2026-09-30']))->assertDontSee('Needs a look');
        $this->page(Employee::factory()->create(['is_active' => false]))->assertDontSee('Needs a look');
    }
}
