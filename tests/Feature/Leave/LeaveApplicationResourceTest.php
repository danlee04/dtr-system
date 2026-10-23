<?php

namespace Tests\Feature\Leave;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\LeaveApplications\Pages\ViewLeaveApplication;
use App\Filament\Widgets\PendingLeaveApplications;
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

    public function test_hr_files_leave_for_an_employee(): void
    {
        Livewire::test(CreateLeaveApplication::class)
            ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('3.000', LeaveApplication::sole()->days);
        $this->assertSame('2.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_the_form_shows_the_day_count_and_pay_split(): void
    {
        Livewire::test(CreateLeaveApplication::class)
            ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
            ->assertSee('3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay');
    }

    public function test_a_refused_date_shows_on_its_field(): void
    {
        Livewire::test(CreateLeaveApplication::class)
            ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-10-30', 'date_to' => '2026-10-30'])
            ->call('create')
            ->assertHasFormErrors(['date_from']);

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_hr_approves_a_pending_application(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)->callAction(TestAction::make('approve')->table($application));

        $this->assertSame(LeaveApplicationStatus::Approved, $application->fresh()->status);
    }

    public function test_hr_cancels_with_a_reason(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ListLeaveApplications::class)
            ->callAction(TestAction::make('cancel')->table($application), ['cancel_reason' => 'Disapproved'])
            ->assertHasNoFormErrors();

        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
        $this->assertSame('5.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_a_cancelled_application_offers_neither_action(): void
    {
        $application = LeaveApplication::factory()->for($this->employee)->create(['status' => LeaveApplicationStatus::Cancelled]);

        Livewire::test(ListLeaveApplications::class)
            ->assertActionHidden(TestAction::make('approve')->table($application))
            ->assertActionHidden(TestAction::make('cancel')->table($application));
    }

    public function test_the_view_page_shows_each_date(): void
    {
        $application = $this->fileOneDay();

        Livewire::test(ViewLeaveApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('Tue, Nov 3, 2026: 1.000')
            ->assertSee('Dela Cruz, Juan S.');
    }

    public function test_applications_are_never_edited_or_deleted(): void
    {
        $application = LeaveApplication::factory()->create();

        $this->assertFalse(LeaveApplicationResource::hasPage('edit'));
        $this->assertFalse(auth()->user()->can('update', $application));
        $this->assertFalse(auth()->user()->can('delete', $application));
    }

    public function test_filing_from_the_leave_tab_picks_the_employee(): void
    {
        Livewire::withQueryParams(['employee' => $this->employee->id])
            ->test(CreateLeaveApplication::class)
            ->assertSchemaStateSet(['employee_id' => $this->employee->id]);
    }

    public function test_the_dashboard_counts_pending_applications(): void
    {
        LeaveApplication::factory()->count(2)->create();
        LeaveApplication::factory()->create(['status' => LeaveApplicationStatus::Approved]);

        Livewire::test(PendingLeaveApplications::class)
            ->assertSee('Pending leave applications')
            ->assertSee('2');
    }

    private function fileOneDay(): LeaveApplication
    {
        return app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);
    }
}
