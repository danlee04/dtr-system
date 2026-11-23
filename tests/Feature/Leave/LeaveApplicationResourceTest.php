<?php

namespace Tests\Feature\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use App\Services\Leave\LeaveLedger;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveApplicationResourceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');
        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '5.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
    }

    public function test_hr_files_leave_for_an_employee_in_a_modal(): void
    {
        Livewire::test(ListLeaveApplications::class)
            ->callAction('fileLeave', ['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
            ->assertHasNoFormErrors()
            ->assertNotified('Leave filed')
            ->assertNoRedirect();

        $this->assertSame('3.000', LeaveApplication::sole()->days);
        $this->assertSame('2.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_the_form_shows_the_day_count_and_pay_split(): void
    {
        Livewire::test(ListLeaveApplications::class)
            ->mountAction('fileLeave')
            ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
            ->assertMountedActionModalSee('3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay');
    }

    public function test_a_refused_date_shows_on_its_field(): void
    {
        Livewire::test(ListLeaveApplications::class)
            ->callAction('fileLeave', ['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-10-30', 'date_to' => '2026-10-30'])
            ->assertHasFormErrors(['date_from']);

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_a_row_offers_view_and_edit_and_the_rest_waits_in_the_application(): void
    {
        $application = $this->fileOneDay();

        // With Print, Approve and Cancel too, the row ran past a 1024px screen (2026-11-23).
        Livewire::test(ListLeaveApplications::class)
            ->assertActionVisible(TestAction::make('view')->table($application))
            ->assertActionVisible(TestAction::make('edit')->table($application))
            ->assertActionDoesNotExist(TestAction::make('approve')->table($application))
            ->assertActionVisible([TestAction::make('view')->table($application), TestAction::make('approve')]);
    }

    public function test_a_cancelled_application_offers_neither_action(): void
    {
        $application = LeaveApplication::factory()->for($this->employee)->create(['status' => LeaveApplicationStatus::Cancelled]);

        Livewire::test(ListLeaveApplications::class)
            ->assertActionHidden([TestAction::make('view')->table($application), TestAction::make('approve')])
            ->assertActionHidden([TestAction::make('view')->table($application), TestAction::make('cancelLeave')]);
    }

    public function test_viewing_shows_each_date_in_a_modal(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->mountAction(TestAction::make('view')->table($application))
            ->assertMountedActionModalSee(['Tue, Nov 3, 2026', 'Dela Cruz, Juan S.', 'Charged to', 'VL 1.000']);
    }

    public function test_hr_approves_from_the_view_modal(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->callAction([TestAction::make('view')->table($application), TestAction::make('approve')]);

        $this->assertSame(LeaveApplicationStatus::Approved, $application->fresh()->status);
    }

    public function test_hr_cancels_from_the_view_modal(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->callAction([TestAction::make('view')->table($application), TestAction::make('cancelLeave')], ['cancel_reason' => 'Withdrawn'])
            ->assertHasNoFormErrors();

        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
        $this->assertSame('5.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_applications_have_no_pages_but_the_list(): void
    {
        $application = LeaveApplication::factory()->create();

        foreach (['create', 'edit', 'view'] as $page) {
            $this->assertFalse(LeaveApplicationResource::hasPage($page), $page);
        }

        // Changed in a modal while pending (HR, 2026-11-23), never deleted.
        $this->assertTrue(auth()->user()->can('update', $application));
        $this->assertFalse(auth()->user()->can('update', LeaveApplication::factory()->create(['status' => LeaveApplicationStatus::Approved])));
        $this->assertFalse(auth()->user()->can('delete', $application));
    }

    public function test_hr_changes_a_pending_application_in_a_modal(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->mountAction(TestAction::make('edit')->table($application))
            ->assertActionDataSet(['date_from' => '2026-11-03', 'date_to' => '2026-11-03'])
            ->setActionData(['date_from' => '2026-11-04', 'date_to' => '2026-11-05'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Leave changed');

        $this->assertSame(['2.000', '2026-11-04'], [$application->fresh()->days, $application->fresh()->date_from->toDateString()]);
        $this->assertSame('3.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_the_change_form_counts_what_the_application_draws_as_on_hand(): void
    {
        // Nov 3–9 is five weekdays: all the VL on hand.
        $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-09', 'coverage' => 'whole_day']);

        Livewire::test(ListLeaveApplications::class)
            ->mountAction(TestAction::make('edit')->table($application))
            ->assertMountedActionModalSee('5.000 days: 5.000 with pay (VL 5.000), 0.000 without pay');
    }

    public function test_hr_disapproves_with_a_reason_from_the_view_modal(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->callAction([TestAction::make('view')->table($application), TestAction::make('disapprove')], ['disapproval_reason' => 'Short of staff'])
            ->assertHasNoFormErrors()
            ->assertNotified('Leave disapproved; the days are back');

        $this->assertSame([LeaveApplicationStatus::Disapproved, 'Short of staff'], [$application->fresh()->status, $application->fresh()->disapproval_reason]);
        $this->assertSame('5.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_a_disapproved_application_offers_no_action_and_shows_why(): void
    {
        $application = $this->fileOneDay();
        app(LeaveApplications::class)->disapprove($application, 'Short of staff');

        $list = Livewire::test(ListLeaveApplications::class)->assertActionHidden(TestAction::make('edit')->table($application));

        foreach (['approve', 'disapprove', 'cancelLeave', 'edit'] as $action) {
            $list->assertActionHidden([TestAction::make('view')->table($application), TestAction::make($action)]);
        }

        $list->mountAction(TestAction::make('view')->table($application))
            ->assertMountedActionModalSee(['Disapproved', 'Short of staff']);
    }

    public function test_an_approved_application_offers_neither_edit_nor_disapprove(): void
    {
        $application = $this->fileOneDay();
        app(LeaveApplications::class)->approve($application);

        Livewire::test(ListLeaveApplications::class)
            ->assertActionHidden(TestAction::make('edit')->table($application))
            ->assertActionHidden([TestAction::make('view')->table($application), TestAction::make('disapprove')]);
    }

    private function fileOneDay(): LeaveApplication
    {
        return app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);
    }
}
