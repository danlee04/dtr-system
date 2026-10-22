<?php

namespace Tests\Feature\Employees;

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\ScheduleAssignmentsRelationManager;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleAssignmentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Schedule $regular;

    private Schedule $fourDay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create();
        $this->regular = Schedule::factory()->create(['code' => 'REG']);
        $this->fourDay = Schedule::factory()->create(['code' => 'CWW']);
    }

    private function manager(): Testable
    {
        return Livewire::test(ScheduleAssignmentsRelationManager::class, [
            'ownerRecord' => $this->employee,
            'pageClass' => EditEmployee::class,
        ]);
    }

    public function test_hr_gives_an_employee_a_schedule(): void
    {
        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'schedule_id' => $this->regular->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertHasNoFormErrors();

        $assignment = $this->employee->scheduleAssignments()->sole();

        $this->assertTrue($assignment->schedule->is($this->regular));
        $this->assertSame('2026-11-01', $assignment->effective_from->toDateString());
        $this->assertNull($assignment->effective_to);
    }

    public function test_a_schedule_without_work_days_is_not_offered(): void
    {
        // Every day of it would be a rest day: nobody would ever be late.
        $night = Schedule::factory()->rosterOnly()->create(['code' => 'N']);

        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'schedule_id' => $night->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertHasFormErrors(['schedule_id' => 'in']);

        $this->assertSame(0, ScheduleAssignment::count());
    }

    public function test_an_overlapping_edit_is_refused(): void
    {
        $regular = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-10-31']);
        ScheduleAssignment::factory()->for($this->employee)->for($this->fourDay)
            ->create(['effective_from' => '2026-11-01', 'effective_to' => null]);

        $this->manager()
            ->callAction(TestAction::make(EditAction::class)->table($regular), [
                'effective_to' => '2026-11-15',
            ])
            ->assertHasFormErrors(['effective_from']);

        $this->assertSame('2026-10-31', $regular->fresh()->effective_to->toDateString());
    }
}
