<?php

namespace Tests\Feature\Employees;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssignScheduleBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_hr_assigns_a_schedule_to_the_selected_employees(): void
    {
        [$first, $second, $notSelected] = Employee::factory()->count(3)->create()->all();
        $fourDay = Schedule::factory()->create(['code' => 'CWW']);

        Livewire::test(ListEmployees::class)
            ->selectTableRecords([$first->id, $second->id])
            ->callAction(TestAction::make('assignSchedule')->table()->bulk(), [
                'schedule_id' => $fourDay->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Schedule assigned to 2 employees');

        $this->assertSame(1, $first->scheduleAssignments()->count());
        $this->assertSame(1, $second->scheduleAssignments()->count());
        $this->assertSame(0, $notSelected->scheduleAssignments()->count());
    }

    public function test_a_conflict_assigns_nobody(): void
    {
        $free = Employee::factory()->create();
        $busy = Employee::factory()->create();
        ScheduleAssignment::factory()->for($busy)
            ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);
        $fourDay = Schedule::factory()->create(['code' => 'CWW']);

        Livewire::test(ListEmployees::class)
            ->selectTableRecords([$free->id, $busy->id])
            ->callAction(TestAction::make('assignSchedule')->table()->bulk(), [
                'schedule_id' => $fourDay->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertNotified('Nobody was assigned');

        $this->assertSame(0, $free->scheduleAssignments()->count());
    }
}
