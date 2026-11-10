<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
