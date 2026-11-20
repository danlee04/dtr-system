<?php

namespace Tests\Feature\Employee;

use App\Enums\DtrSlot;
use App\Enums\EmploymentStatus;
use App\Filament\Employee\Pages\MyDtr;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\ManualTap;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\DtrDeduction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyDtrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // All of October has happened.
        $this->travelTo('2026-11-03 09:00');
    }

    /** Someone on 8–12 and 1–5, Monday to Friday, signed in with their own account. */
    private function signedInEmployee(EmploymentStatus $status = EmploymentStatus::Permanent, ?User $account = null): Employee
    {
        $employee = Employee::factory()->create(['employment_status' => $status]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);
        $account ??= User::factory()->employee($employee)->create();
        $account->update(['employee_id' => $employee->id]);
        $this->actingAs($account);

        return $employee;
    }

    /** @param  array<string, ?string>  $times  the date's printed columns */
    private function upload(Employee $employee, string $month, string $date, array $times): DtrUpload
    {
        $start = "{$month}-01";

        return DtrUpload::factory()->for($employee)
            ->state(['period_start' => $start, 'period_end' => date('Y-m-t', strtotime($start))])
            ->has(DtrDay::factory()->state(['date' => $date, ...$times]), 'days')
            ->create();
    }

    public function test_an_employee_sees_their_own_dtr_read_only(): void
    {
        $employee = $this->signedInEmployee();
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14', 'am_departure' => '12:00', 'pm_arrival' => '12:58', 'pm_departure' => null]);
        ManualTap::factory()->for($employee)->create(['date' => '2026-10-02', 'slot' => DtrSlot::PmDeparture, 'time' => '17:00']);

        Livewire::test(MyDtr::class)
            ->assertSee(['October 2026', '8:14 AM', '5:00 PM', 'HR: forgot to tap', 'Something wrong? Bring your signed DTR to HR'])
            ->assertDontSee('Add P.M. departure, Fri, Oct 2');
    }

    public function test_another_employees_dtr_cannot_be_opened_by_changing_the_month(): void
    {
        $employee = $this->signedInEmployee();
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14']);
        $this->upload(Employee::factory()->create(), '2026-09', '2026-09-01', ['am_arrival' => '06:11']);

        // September is someone else's; the month sent from the browser only picks among their own.
        Livewire::test(MyDtr::class)
            ->set('month', '2026-09')
            ->assertSee(['October 2026', '8:14 AM'])
            ->assertDontSee('6:11 AM');
    }

    public function test_the_month_picker_opens_an_older_dtr_of_theirs(): void
    {
        $employee = $this->signedInEmployee();
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14']);
        $this->upload($employee, '2026-09', '2026-09-01', ['am_arrival' => '07:33']);

        Livewire::test(MyDtr::class)
            ->assertSee('October 2026')
            ->set('month', '2026-09')
            ->assertSee(['September 2026', '7:33 AM'])
            ->assertDontSee('8:14 AM');
    }

    public function test_an_hr_account_linked_to_an_employee_cannot_fill_blanks_here(): void
    {
        $employee = $this->signedInEmployee(account: User::factory()->create());
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14', 'am_departure' => '12:00', 'pm_arrival' => '12:58', 'pm_departure' => null]);

        Livewire::test(MyDtr::class)
            ->assertSee('No time out')
            ->assertDontSee('Add P.M. departure, Fri, Oct 2');
    }

    public function test_a_job_order_dtr_is_shown_as_printed(): void
    {
        $employee = $this->signedInEmployee(EmploymentStatus::JobOrder);
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14']);

        Livewire::test(MyDtr::class)
            ->assertSee(['8:14 AM', 'earn no leave credits']);
    }

    public function test_the_dashboard_shows_the_latest_dtr_with_a_way_to_check_it(): void
    {
        $employee = $this->signedInEmployee();
        $this->upload($employee, '2026-09', '2026-09-01', ['am_arrival' => '08:30', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
        $this->upload($employee, '2026-10', '2026-10-02', ['am_arrival' => '08:14', 'am_departure' => '12:00', 'pm_arrival' => '12:58', 'pm_departure' => '17:00']);

        $this->get('/')
            ->assertOk()
            ->assertSee(['Your DTR for October', '14 min', MyDtr::getUrl()])
            ->assertDontSee('30 min');
    }

    public function test_with_no_upload_yet_the_page_says_so(): void
    {
        $this->signedInEmployee();

        Livewire::test(MyDtr::class)
            ->assertSee('No DTR uploaded for you yet');
    }

    public function test_an_employee_sees_that_their_dtr_is_final(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $employee = $this->signedInEmployee();
        $upload = $this->upload($employee, '2026-10', '2026-10-01', ['am_arrival' => '08:14', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
        app(DtrDeduction::class)->finalize($upload, User::factory()->create(['name' => 'Ana Reyes']));

        // 14 minutes late is 0.029; the other 21 work days are absent: October's 1.250 SL, then 19.750 VL.
        Livewire::test(MyDtr::class)
            ->assertSee(["Final · Nov 3, 2026 by Ana Reyes · SL \u{2212}1.250 · VL \u{2212}19.779"])
            ->assertDontSee(['Mark final', 'Reopen']);
    }
}
