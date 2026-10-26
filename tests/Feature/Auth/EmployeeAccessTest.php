<?php

namespace Tests\Feature\Auth;

use App\Enums\LeaveApplicationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\DutyRoster;
use App\Filament\Pages\LeaveOpeningBalances;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PendingLeaveApplications;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_use_the_same_panel_but_never_reach_hr_screens(): void
    {
        $this->actingAs(User::factory()->employee()->create());

        $this->get('/')->assertOk();

        foreach ([
            EmployeeResource::getUrl('index'),
            LeaveApplicationResource::getUrl('index'),
            HolidayResource::getUrl('index'),
            OfficeResource::getUrl('index'),
            ScheduleResource::getUrl('index'),
            UserResource::getUrl('index'),
            DutyRoster::getUrl(),
            LeaveOpeningBalances::getUrl(),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_the_pending_count_on_the_dashboard_is_for_staff(): void
    {
        // Widgets load after the page, so their gate is what the dashboard asks.
        $this->actingAs(User::factory()->create());
        $this->assertTrue(PendingLeaveApplications::canView());

        $this->actingAs(User::factory()->employee()->create());
        $this->assertFalse(PendingLeaveApplications::canView());
    }

    public function test_who_may_use_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $employeeAccount = User::factory()->employee()->create();

        $this->assertTrue(User::factory()->create()->canAccessPanel($panel));
        $this->assertTrue($employeeAccount->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create(['role' => UserRole::Employee])->canAccessPanel($panel));
        $this->assertFalse(User::factory()->inactive()->create()->canAccessPanel($panel));

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

        // An office or schedule nobody uses is one staff may delete; never an employee.
        foreach ([Employee::factory()->create(), Office::factory()->create(), Schedule::factory()->create(), Holiday::factory()->create(), RosterEntry::factory()->create(), ScheduleAssignment::factory()->create()] as $record) {
            $this->assertFalse($user->can('update', $record), $record::class);
            $this->assertFalse($user->can('delete', $record), $record::class);
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
        $this->actingAs(User::factory()->employee()->create());

        Livewire::test(ListLeaveApplications::class)->assertForbidden();
    }
}
