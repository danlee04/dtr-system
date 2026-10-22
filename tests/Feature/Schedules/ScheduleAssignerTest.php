<?php

namespace Tests\Feature\Schedules;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Attendance\ScheduleAssigner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ScheduleAssignerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Schedule $regular;

    private Schedule $fourDay;

    private ScheduleAssigner $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create();
        $this->regular = Schedule::factory()->create(['code' => 'REG']);
        $this->fourDay = Schedule::factory()->create(['code' => 'CWW']);
        $this->assigner = app(ScheduleAssigner::class);
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_a_new_schedule_ends_the_open_one_the_day_before(): void
    {
        $old = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-01-01', 'effective_to' => null]);

        $new = $this->assigner->assign($this->employee, $this->fourDay, $this->day('2026-11-01'), null);

        // October stays on Regular; November onwards is the 4-Day Week.
        $this->assertSame('2026-10-31', $old->fresh()->effective_to->toDateString());
        $this->assertSame('2026-11-01', $new->effective_from->toDateString());
        $this->assertNull($new->effective_to);
    }

    public function test_ending_the_open_assignment_is_recorded(): void
    {
        $old = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-01-01', 'effective_to' => null]);

        $this->assigner->assign($this->employee, $this->fourDay, $this->day('2026-11-01'), null);

        // Through the model, not a query-builder update, or the log never sees it.
        $activity = Activity::query()
            ->where('subject_type', $old->getMorphClass())
            ->where('subject_id', $old->id)
            ->where('event', 'updated')
            ->sole();

        $this->assertArrayHasKey('effective_to', $activity->attribute_changes['attributes']);
    }

    public function test_a_period_inside_a_closed_assignment_is_a_conflict(): void
    {
        $closed = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-12-31']);

        $conflict = $this->assigner->conflict($this->employee, $this->day('2026-11-01'), null);

        $this->assertTrue($conflict->is($closed));
    }

    public function test_an_open_assignment_that_starts_later_is_a_conflict(): void
    {
        $later = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

        $conflict = $this->assigner->conflict($this->employee, $this->day('2026-11-01'), null);

        $this->assertTrue($conflict->is($later));
    }

    public function test_a_period_that_ends_before_a_later_assignment_fits(): void
    {
        ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

        $this->assertNull($this->assigner->conflict($this->employee, $this->day('2026-11-01'), $this->day('2026-11-30')));
    }

    public function test_an_edit_may_overlap_nothing_but_itself(): void
    {
        $regular = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
            ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-10-31']);
        $fourDay = ScheduleAssignment::factory()->for($this->employee)->for($this->fourDay)
            ->create(['effective_from' => '2026-11-01', 'effective_to' => null]);

        // Stretching Regular into November runs into the 4-Day Week.
        $conflict = $this->assigner->conflict($this->employee, $this->day('2026-01-01'), $this->day('2026-11-15'), editing: $regular);
        $this->assertTrue($conflict->is($fourDay));

        // An open-ended assignment is not cut short by editing it.
        $this->assertNull($this->assigner->conflict($this->employee, $this->day('2026-11-01'), null, editing: $fourDay));
    }

    public function test_one_conflict_assigns_nobody(): void
    {
        $free = Employee::factory()->create();
        $busy = Employee::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
        ScheduleAssignment::factory()->for($busy)->for($this->regular)
            ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

        $problems = $this->assigner->assignAll(collect([$free, $busy]), $this->fourDay, $this->day('2026-11-01'), null);

        $this->assertSame(['Dela Cruz, Juan S.: already on REG from Dec 1, 2026, with no end date.'], $problems);
        $this->assertSame(0, $free->scheduleAssignments()->count());
    }
}
