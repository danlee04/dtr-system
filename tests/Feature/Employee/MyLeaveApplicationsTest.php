<?php

namespace Tests\Feature\Employee;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LedgerKind;
use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use App\Filament\Employee\Resources\MyLeaveApplications\Pages\ListMyLeaveApplications;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyLeaveApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');

        $this->employee = Employee::factory()->create(['last_name' => 'Dela Cruz']);
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);

        foreach (['VL' => '5.000', 'SL' => '2.000'] as $type => $days) {
            LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => $type, 'days' => $days, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        }

        $this->user = User::factory()->employee($this->employee)->create();
        $this->actingAs($this->user);
    }

    public function test_an_employee_files_their_own_leave_in_a_modal(): void
    {
        Livewire::test(ListMyLeaveApplications::class)
            ->callAction('fileLeave', ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-04', 'division_head' => 'Dr. Ana Reyes'])
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $application = LeaveApplication::sole();
        $this->assertTrue($application->employee->is($this->employee));
        $this->assertSame($this->user->id, $application->filed_by_user_id);
        $this->assertSame('Dr. Ana Reyes', $application->division_head);
    }

    public function test_the_form_previews_the_employees_own_days(): void
    {
        Livewire::test(ListMyLeaveApplications::class)
            ->mountAction('fileLeave')
            ->fillForm(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-04'])
            ->assertMountedActionModalSee('2.000 days: 2.000 with pay (VL 2.000), 0.000 without pay');
    }

    public function test_an_employee_never_sees_someone_elses_application(): void
    {
        $theirs = LeaveApplication::factory()->create();
        $component = Livewire::test(ListMyLeaveApplications::class)->assertCanNotSeeTableRecords([$theirs]);

        try {
            $component->mountAction(TestAction::make('view')->table($theirs));
        } catch (\Throwable) {
            // Refused outright is as good as ignored; what matters is that no modal opened.
        }

        $this->assertSame([], $component->instance()->mountedActions);
        $this->assertFalse(MyLeaveApplicationResource::hasPage('view'));
    }

    public function test_an_employee_views_and_cancels_from_the_modal(): void
    {
        $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);

        Livewire::test(ListMyLeaveApplications::class)
            ->mountAction(TestAction::make('view')->table($application))
            ->assertMountedActionModalSee(['Tue, Nov 3, 2026', 'VL 1.000'])
            // Their own leave: no need to name them.
            ->assertMountedActionModalDontSee('Dela Cruz');

        Livewire::test(ListMyLeaveApplications::class)
            ->callAction([TestAction::make('view')->table($application), TestAction::make('cancelLeave')], ['cancel_reason' => 'Plans changed']);

        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
    }

    public function test_an_employee_cannot_cancel_someone_elses_leave_through_livewire(): void
    {
        $theirs = LeaveApplication::factory()->create();

        try {
            Livewire::test(ListMyLeaveApplications::class)
                ->callAction(TestAction::make('cancel')->table($theirs), ['cancel_reason' => 'Not mine']);
        } catch (\Throwable) {
            // Refused outright is as good as ignored; only the outcome matters.
        }

        $this->assertSame(LeaveApplicationStatus::Pending, $theirs->fresh()->status);
    }

    public function test_an_employee_cancels_their_pending_leave(): void
    {
        $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);

        Livewire::test(ListMyLeaveApplications::class)
            ->callAction(TestAction::make('cancel')->table($application), ['cancel_reason' => 'Plans changed'])
            ->assertHasNoFormErrors();

        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
    }

    public function test_an_approved_application_cannot_be_cancelled_or_approved_by_the_employee(): void
    {
        $application = LeaveApplication::factory()->for($this->employee)->create(['status' => LeaveApplicationStatus::Approved]);

        Livewire::test(ListMyLeaveApplications::class)
            ->assertActionHidden(TestAction::make('cancel')->table($application))
            ->assertActionDoesNotExist(TestAction::make('approve')->table($application));
    }
}
