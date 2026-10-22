<?php

namespace Tests\Feature\Roster;

use App\Models\Employee;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Attendance\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    private Employee $juan;

    private Employee $maria;

    private Employee $reyes;

    private Schedule $am;

    private Schedule $pm;

    private Schedule $night;

    private CarbonImmutable $november;

    private Roster $roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
        $this->juan = Employee::factory()->for($this->nursing)->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
        $this->maria = Employee::factory()->for($this->nursing)->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => 'Lopez']);
        $this->reyes = Employee::factory()->create(['last_name' => 'Reyes']); // another office
        $this->am = $this->shift('AM', '06:00:00', '14:00:00');
        $this->pm = $this->shift('PM', '14:00:00', '22:00:00');
        $this->night = $this->shift('N', '22:00:00', '06:00:00');
        $this->november = CarbonImmutable::parse('2026-11-01');
        $this->roster = app(Roster::class);
    }

    private function shift(string $code, string $in, string $out): Schedule
    {
        return Schedule::factory()->rosterOnly()->create([
            'code' => $code,
            'block1_in' => $in,
            'block1_out' => $out,
            'block2_in' => null,
            'block2_out' => null,
        ]);
    }

    public function test_it_reads_the_month_as_a_grid_of_codes(): void
    {
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);
        // The last day: a plain date comparison would miss it on SQLite.
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-30', 'schedule_id' => null]);

        $grid = $this->roster->read($this->nursing, $this->november);

        $this->assertSame([$this->juan->id, $this->maria->id], array_keys($grid));
        $this->assertCount(30, $grid[$this->juan->id]);
        $this->assertSame('AM', $grid[$this->juan->id][1]);
        $this->assertSame('', $grid[$this->juan->id][2]);
        $this->assertSame('OFF', $grid[$this->juan->id][30]);
    }

    public function test_saving_sets_codes_and_days_off_ignoring_case(): void
    {
        $changes = $this->roster->save($this->nursing, $this->november, [
            $this->juan->id => [1 => 'am', 2 => ' n ', 3 => 'off'],
        ]);

        $grid = $this->roster->read($this->nursing, $this->november);

        $this->assertSame(3, $changes);
        $this->assertSame(['AM', 'N', 'OFF'], [$grid[$this->juan->id][1], $grid[$this->juan->id][2], $grid[$this->juan->id][3]]);
    }

    public function test_a_blank_cell_removes_its_entry(): void
    {
        // Back to the employee's fixed schedule, if they have one.
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-05', 'schedule_id' => $this->pm->id]);

        $changes = $this->roster->save($this->nursing, $this->november, [$this->juan->id => [5 => '']]);

        $this->assertSame(1, $changes);
        $this->assertSame(0, RosterEntry::count());
    }

    public function test_a_cell_that_was_not_sent_is_left_alone(): void
    {
        // Maria joined the office after the page was opened: her row was never
        // on it, so saving the page must not empty her month.
        RosterEntry::factory()->for($this->maria)->create(['date' => '2026-11-05', 'schedule_id' => $this->pm->id]);

        $this->roster->save($this->nursing, $this->november, [$this->juan->id => [5 => 'AM']]);

        $this->assertSame(2, RosterEntry::count());
    }

    public function test_an_unknown_code_saves_nothing(): void
    {
        try {
            $this->roster->save($this->nursing, $this->november, [
                $this->juan->id => [1 => 'AM', 2 => 'NN'],
            ]);
            $this->fail('An unknown code was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                "cells.{$this->juan->id}.2" => ['"NN" for Dela Cruz, Juan S. on Nov 2 is not a schedule code.'],
            ], $exception->errors());
        }

        $this->assertSame(0, RosterEntry::count());
    }

    public function test_saving_an_unchanged_grid_changes_nothing(): void
    {
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);
        $logged = Activity::count();

        $changes = $this->roster->save($this->nursing, $this->november, $this->roster->read($this->nursing, $this->november));

        $this->assertSame(0, $changes);
        $this->assertSame($logged, Activity::count());
    }

    public function test_cells_outside_the_office_or_the_month_are_ignored(): void
    {
        $changes = $this->roster->save($this->nursing, $this->november, [
            $this->reyes->id => [1 => 'AM'],
            $this->juan->id => [31 => 'AM'],
        ]);

        $this->assertSame(0, $changes);
        $this->assertSame(0, RosterEntry::count());
    }

    public function test_changing_a_cell_is_recorded(): void
    {
        $entry = RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);

        $this->roster->save($this->nursing, $this->november, [$this->juan->id => [1 => 'PM']]);

        $activity = Activity::query()
            ->where('subject_type', $entry->getMorphClass())
            ->where('event', 'updated')
            ->sole();

        $this->assertSame($this->pm->id, $activity->attribute_changes['attributes']['schedule_id']);
    }
}
