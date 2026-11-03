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

    private function mariaInPharmacy(): Employee
    {
        return Employee::factory()->for(Office::factory()->create(['name' => 'Pharmacy']))
            ->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => null]);
    }

    public function test_hr_opens_the_duty_roster(): void
    {
        $this->get(DutyRoster::getUrl())->assertOk();
    }

    public function test_every_active_employee_is_listed_with_the_month_loaded(): void
    {
        RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);
        $this->mariaInPharmacy();

        Livewire::test(DutyRoster::class)
            ->set('month', '2026-11')
            ->assertSet("cells.{$this->juan->id}.1", 'AM')
            ->assertSee(['Dela Cruz, Juan S.', 'Santos, Maria']);
    }

    public function test_the_office_filter_hides_rows_but_keeps_what_was_typed_in_them(): void
    {
        $maria = $this->mariaInPharmacy();

        Livewire::test(DutyRoster::class)
            ->set('month', '2026-11')
            ->set("cells.{$maria->id}.2", 'AM')
            ->set('officeId', $this->nursing->id)
            ->assertSee('Dela Cruz, Juan S.')
            ->assertDontSee('Santos, Maria')
            ->assertSee('1 unsaved change')
            ->call('save')
            ->assertNotified('Roster saved: 1 change');

        $this->assertSame($maria->id, RosterEntry::sole()->employee_id);
    }

    public function test_saving_the_grid_writes_the_roster(): void
    {
        Livewire::test(DutyRoster::class)
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
            ->set('month', '2026-11')
            ->set("cells.{$this->juan->id}.2", 'NN')
            ->call('save')
            ->assertHasErrors(["cells.{$this->juan->id}.2"]);

        $this->assertSame(0, RosterEntry::count());
    }

    public function test_the_month_stays_put_while_cells_are_unsaved(): void
    {
        Livewire::test(DutyRoster::class)
            ->set('month', '2026-11')
            ->set("cells.{$this->juan->id}.2", 'N')
            ->set('month', '2026-12')
            ->assertSet('month', '2026-11')
            ->assertSet("cells.{$this->juan->id}.2", 'N')
            ->assertNotified('Save the roster first');
    }

    public function test_the_grid_comes_a_page_at_a_time(): void
    {
        // With Dela Cruz, eleven rows: Ason to Ison fill the first page of ten.
        foreach (range('A', 'J') as $letter) {
            Employee::factory()->for($this->nursing)->create(['last_name' => "{$letter}son", 'first_name' => 'Pat', 'middle_name' => null]);
        }

        Livewire::test(DutyRoster::class)
            ->set('perPage', 10)
            ->assertSee('Ison, Pat')
            ->assertDontSee('Json, Pat')
            ->call('nextPage')
            ->assertSee('Json, Pat');
    }
}
