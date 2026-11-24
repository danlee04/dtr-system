<?php

namespace Tests\Feature\Auth;

use App\Enums\LeaveApplicationStatus;
use App\Enums\UserRole;
use App\Filament\Employee\Pages\History;
use App\Filament\Pages\DutyRoster;
use App\Filament\Pages\HabitualTardiness;
use App\Filament\Pages\LeaveOpeningBalances;
use App\Filament\Pages\MonthlySummary;
use App\Filament\Resources\DayExceptions\DayExceptionResource;
use App\Filament\Resources\DtrUploads\DtrUploadResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\DtrUpload;
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
            MonthlySummary::getUrl(),
            HabitualTardiness::getUrl(),
            DtrUploadResource::getUrl('index'),
            DayExceptionResource::getUrl('index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_the_topbar_shows_what_is_waiting_to_staff_only(): void
    {
        // A pending application by someone with no schedule and no opening balance yet, and a DTR whose name matched nobody.
        LeaveApplication::factory()->create();
        DtrUpload::factory()->unmatched()->create();

        // The bell lists each kind; New opens Upload DTRs, File leave, OB/TO/gate pass and New employee on their pages,
        // and Upload DTRs has no button of its own any more (HR, 2026-11-23).
        $this->actingAs(User::factory()->create())
            ->get(EmployeeResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Waiting for you: 4')
            ->assertSee(['Leave applications to approve', 'DTRs that need an employee', 'DTRs not final yet', 'Staff with no schedule', 'Staff with no opening balance'])
            ->assertSeeInOrder(['Waiting for you', 'Upload DTRs', 'File leave', 'OB, TO or gate pass'])
            ->assertSee([
                DtrUploadResource::getUrl('index', ['action' => 'uploadDtrs']),
                LeaveApplicationResource::getUrl('index', ['action' => 'fileLeave']),
                DayExceptionResource::getUrl('index', ['action' => 'create']),
                EmployeeResource::getUrl('index', ['action' => 'create']),
            ]);

        $this->actingAs(User::factory()->employee()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Waiting for you')
            ->assertDontSee('Upload DTRs');
    }

    public function test_employees_have_no_topbar_and_staff_keep_theirs(): void
    {
        // An employee's pages start at the top, the sidebar's button on a phone (HR, 2026-11-23).
        $this->actingAs(User::factory()->employee()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSeeHtml('class="fi-topbar')
            ->assertSeeHtml('fi-layout-sidebar-toggle-btn');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSeeHtml('fi-topbar');
    }

    public function test_employees_have_one_notifications_bell_beside_each_page_title_and_staff_have_none(): void
    {
        // Right of the title, once, on the dashboard (its own heading) and on the other pages (2026-11-24); not in the sidebar.
        $this->actingAs(User::factory()->employee()->create());

        foreach (['/', History::getUrl()] as $url) {
            $page = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($page, 'class="dtr-notifications-bell"'), $url);
            $this->assertStringNotContainsString('class="fi-sidebar-footer"', $page);
        }

        // Staff have "Waiting for you" in the topbar; a second bell would say a different thing in the same place.
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertDontSeeHtml('class="dtr-notifications-bell"');
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
