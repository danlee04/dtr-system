<?php

namespace Tests\Feature\Leave;

use App\Enums\LedgerKind;
use App\Filament\Pages\LeaveOpeningBalances;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveOpeningBalancesPageTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Employee $juan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        config(['leave.start_date' => '2026-11-01']);

        $this->office = Office::factory()->create();
        $this->juan = Employee::factory()->for($this->office)->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
    }

    public function test_hr_opens_the_page_and_sees_the_as_of_date(): void
    {
        $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Balances as of Oct 31, 2026');
    }

    public function test_choosing_an_office_loads_its_grid(): void
    {
        LeaveLedgerEntry::factory()->for($this->juan)->create(['leave_type' => 'VL', 'days' => '10', 'kind' => LedgerKind::Opening, 'posting_key' => "opening:{$this->juan->id}:VL"]);

        Livewire::test(LeaveOpeningBalances::class)
            ->set('officeId', $this->office->id)
            ->assertSet("rows.{$this->juan->id}.VL", '10.000')
            ->assertSee('Dela Cruz, Juan S.');
    }

    public function test_saving_the_grid_writes_the_rows(): void
    {
        Livewire::test(LeaveOpeningBalances::class)
            ->set('officeId', $this->office->id)
            ->set("rows.{$this->juan->id}.SL", '7.5')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Opening balances saved: 1 change');

        $this->assertSame('7.500', $this->juan->leaveLedgerEntries()->sole()->days);
    }

    public function test_a_bad_cell_is_marked(): void
    {
        Livewire::test(LeaveOpeningBalances::class)
            ->set('officeId', $this->office->id)
            ->set("rows.{$this->juan->id}.SL", 'abc')
            ->call('save')
            ->assertHasErrors(["rows.{$this->juan->id}.SL"]);

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_without_a_start_date_the_page_says_so_and_saves_nothing(): void
    {
        config(['leave.start_date' => null]);

        $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Set LEAVE_START_DATE');

        Livewire::test(LeaveOpeningBalances::class)
            ->set('officeId', $this->office->id)
            ->set("rows.{$this->juan->id}.SL", '7.5')
            ->call('save');

        $this->assertSame(0, LeaveLedgerEntry::count());
    }
}
