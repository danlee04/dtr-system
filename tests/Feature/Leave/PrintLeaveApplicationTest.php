<?php

namespace Tests\Feature\Leave;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrintLeaveApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private LeaveApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');
        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()
            ->for(Office::factory()->create(['name' => 'Nursing Service']))
            ->create(['position' => 'Nurse II']);
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);

        foreach (['VL' => '5.000', 'SL' => '2.000'] as $type => $days) {
            LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => $type, 'days' => $days, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        }

        $this->application = app(LeaveApplications::class)->file($this->employee, [
            'leave_type' => 'SL',
            'date_from' => '2026-11-03',
            'date_to' => '2026-11-05',
            'coverage' => 'whole_day',
            'division_head' => 'Dr. Ana Reyes',
        ]);
    }

    public function test_hr_prints_a_filled_form(): void
    {
        $this->get(route('leave-applications.print', $this->application))
            ->assertOk()
            ->assertSeeInOrder(['HRM-07', 'Nursing Service', $this->employee->last_name, 'Nurse II', '3.000', 'Nov 3–5, 2026'], escape: false)
            ->assertSee('data-checked="SL"', escape: false)
            ->assertSeeInOrder(['2.000', '2.000', '0.000'])
            ->assertSee('Mary Jane Lao Guico')
            ->assertSee('Dr. Ana Reyes')
            ->assertSee('Edhel S. Miro, MD, DPCAM, MM-PA');
    }

    public function test_wellness_leave_prints_under_others(): void
    {
        $wellness = LeaveApplication::factory()->for($this->employee)->create(['leave_type' => LeaveType::Wellness]);

        $this->get(route('leave-applications.print', $wellness))
            ->assertSee('data-checked="others"', escape: false)
            ->assertSee('Wellness Leave');
    }

    public function test_the_employee_prints_their_own_form(): void
    {
        $this->actingAs(User::factory()->employee($this->employee)->create())
            ->get(route('leave-applications.print', $this->application))
            ->assertOk();
    }

    public function test_an_employee_cannot_print_someone_elses_form(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route('leave-applications.print', $this->application))
            ->assertForbidden();
    }

    public function test_the_hr_list_offers_the_print_action(): void
    {
        Livewire::test(ListLeaveApplications::class)
            ->assertActionVisible(TestAction::make('print')->table($this->application));
    }
}
