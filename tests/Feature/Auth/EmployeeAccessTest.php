<?php

namespace Tests\Feature\Auth;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_reach_the_hr_panel_and_employees_do_not(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
        $this->actingAs(User::factory()->employee()->create())->get('/')->assertForbidden();
    }

    public function test_only_linked_active_accounts_may_use_the_employee_panel(): void
    {
        $panel = Panel::make()->id('employee');
        $employeeAccount = User::factory()->employee()->create();

        $this->assertTrue($employeeAccount->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create(['employee_id' => Employee::factory()->create()->id])->canAccessPanel($panel));

        $employeeAccount->employee->update(['date_separated' => today()->subDay()]);
        $this->assertFalse($employeeAccount->fresh()->canAccessPanel($panel));
    }

    public function test_employees_have_no_staff_abilities(): void
    {
        $user = User::factory()->employee()->create();

        foreach ([Employee::class, Office::class, Schedule::class, Holiday::class, RosterEntry::class, ScheduleAssignment::class, LeaveLedgerEntry::class] as $model) {
            $this->assertFalse($user->can('viewAny', $model), $model);
            $this->assertFalse($user->can('create', $model), $model);
        }
    }

    public function test_an_employee_acts_only_on_their_own_applications(): void
    {
        $user = User::factory()->employee()->create();
        $own = LeaveApplication::factory()->for($user->employee)->create();
        $theirs = LeaveApplication::factory()->create();

        $this->assertTrue($user->can('view', $own));
        $this->assertTrue($user->can('print', $own));
        $this->assertTrue($user->can('cancel', $own));
        $this->assertFalse($user->can('approve', $own));
        $this->assertFalse($user->can('view', $theirs));
        $this->assertFalse($user->can('cancel', $theirs));

        $own->update(['status' => LeaveApplicationStatus::Approved]);
        $this->assertFalse($user->can('cancel', $own->fresh()));
    }

    public function test_the_hr_leave_list_never_opens_for_an_employee(): void
    {
        // The panel turns them away first; this holds even if it did not.
        $this->actingAs(User::factory()->employee()->create());

        Livewire::test(ListLeaveApplications::class)->assertForbidden();
    }
}
