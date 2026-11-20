<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\DtrDeduction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-03 09:00');
    }

    public function test_staff_see_the_office_at_a_glance(): void
    {
        $juan = Employee::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => null]);
        LeaveApplication::factory()->for($juan)->create();

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee(['Late and undertime', 'Leave to approve', 'Quick actions', 'Out today', 'Recent leave', 'Dela Cruz, Juan']);
    }

    public function test_the_sidebar_has_the_overview_group_a_theme_switch_and_the_user_with_sign_out(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ana Santos']));

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Overview', 'Dashboard'])
            ->assertSee(['Switch to dark mode', 'Ana Santos', 'Sign out', route('filament.admin.auth.profile'), route('filament.admin.auth.logout')]);

        // The button beside the name signs out, with no menu to open first.
        $this->post(route('filament.admin.auth.logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_employees_see_their_own_dashboard_not_the_offices(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Leave balance')
            ->assertDontSee(['Leave to approve', 'Out today', 'Recent leave']);
    }

    public function test_the_dashboard_shows_how_many_dtrs_are_final_and_the_offices_not_done(): void
    {
        $nursing = Office::factory()->create(['name' => 'Nursing Service']);

        foreach (['Abad', 'Bautista'] as $lastName) {
            $employee = Employee::factory()->for($nursing)->create(['last_name' => $lastName]);
            ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-09-01']);
            DtrUpload::factory()->for($employee)->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-01'])
                ->days()->create(['date' => '2026-10-01', 'am_arrival' => '08:00', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
        }

        $hr = User::factory()->create();
        app(DtrDeduction::class)->finalize(DtrUpload::query()->whereRelation('employee', 'last_name', 'Abad')->sole(), $hr);

        $this->actingAs($hr)
            ->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Uploaded', '2 of', 'Final', '1 of 2'])
            ->assertSee(['Offices not done', 'Nursing Service', 'final 1']);
    }

    public function test_staff_choose_the_month_the_dashboard_shows(): void
    {
        $this->actingAs(User::factory()->create());
        DtrUpload::factory()->create(['period_start' => '2026-08-01', 'period_end' => '2026-08-31']);

        // Last month by default: October, as IT prints a month once it is over. The select lists it and every month with a DTR.
        Livewire::test(Dashboard::class)
            ->assertSet('month', '2026-10')
            ->assertSeeHtmlInOrder(['<option value="2026-10">October 2026</option>', '<option value="2026-08">August 2026</option>'])
            ->assertSee('DTRs for October')
            ->set('month', '2026-09')
            ->assertSee('DTRs for September')
            // Anything but a "Y-m" month falls back to last month.
            ->set('month', 'not-a-month')
            ->assertSee('DTRs for October');
    }
}
