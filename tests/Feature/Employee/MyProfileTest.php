<?php

namespace Tests\Feature\Employee;

use App\Enums\EmploymentStatus;
use App\Filament\Pages\Auth\ChangePassword;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): Employee
    {
        $employee = Employee::factory()->for(Office::factory()->state(['name' => 'Nursing Service']))->create([
            'employee_number' => '2019-0142',
            'position' => 'Nurse II',
            'employment_status' => EmploymentStatus::Permanent,
            'date_hired' => '2019-03-04',
        ]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory()->state(['name' => 'Regular Schedule']))->create(['effective_from' => '2026-01-01']);

        return $employee;
    }

    public function test_an_employee_sees_their_own_details_on_their_profile(): void
    {
        $employee = $this->employee();
        $this->actingAs(User::factory()->employee($employee)->create());

        Livewire::test(ChangePassword::class)
            ->assertSee([$employee->full_name, '2019-0142', 'Nursing Service', 'Nurse II', 'Permanent', 'Mar 4, 2019', 'Regular Schedule']);
    }

    public function test_the_details_cannot_be_changed_from_the_profile(): void
    {
        $employee = $this->employee();
        $this->actingAs(User::factory()->employee($employee)->create());

        // Only the photo is the employee's to change; a position sent anyway is not saved.
        Livewire::test(ChangePassword::class)
            ->set('data.employee.position', 'Chief Nurse')
            ->set('data.employee.employee_number', '0000')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Nurse II', '2019-0142'], [$employee->fresh()->position, $employee->fresh()->employee_number]);
    }

    public function test_staff_without_an_employee_see_only_their_account(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'hr@example.test']));

        Livewire::test(ChangePassword::class)
            ->assertSee('hr@example.test')
            ->assertDontSee('Employment');
    }
}
