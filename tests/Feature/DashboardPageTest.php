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

    public function test_employees_keep_their_own_dashboard(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Quick actions')
            ->assertDontSee('Recent leave');
    }
}
