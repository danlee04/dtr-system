<?php

namespace Tests\Feature\Roster;

use App\Filament\Pages\DutyRoster;
use App\Models\Employee;
use App\Models\Office;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DutyRosterPageTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    private Employee $juan;

    private Schedule $am;

    private Schedule $night;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
        $this->juan = Employee::factory()->for($this->nursing)->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
        $this->am = Schedule::factory()->rosterOnly()->create(['code' => 'AM']);
        $this->night = Schedule::factory()->rosterOnly()->create(['code' => 'N']);
    }

    public function test_hr_opens_the_duty_roster(): void
    {
        $this->get(DutyRoster::getUrl())->assertOk();
    }

    public function test_choosing_an_office_and_a_month_loads_its_grid(): void
    {
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);

        Livewire::test(DutyRoster::class)
            ->set('officeId', $this->nursing->id)
            ->set('month', '2026-11')
            ->assertSet("cells.{$this->juan->id}.1", 'AM')
            ->assertSee('Dela Cruz, Juan S.');
    }

    public function test_saving_the_grid_writes_the_roster(): void
    {
        Livewire::test(DutyRoster::class)
            ->set('officeId', $this->nursing->id)
            ->set('month', '2026-11')
            ->set("cells.{$this->juan->id}.2", 'n')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Roster saved: 1 change');

        $entry = RosterEntry::sole();

        $this->assertSame('2026-11-02', $entry->date->toDateString());
        $this->assertSame($this->night->id, $entry->schedule_id);
    }

    public function test_an_unknown_code_marks_its_cell(): void
    {
        Livewire::test(DutyRoster::class)
            ->set('officeId', $this->nursing->id)
            ->set('month', '2026-11')
            ->set("cells.{$this->juan->id}.2", 'NN')
            ->call('save')
            ->assertHasErrors(["cells.{$this->juan->id}.2"]);

        $this->assertSame(0, RosterEntry::count());
    }
}
