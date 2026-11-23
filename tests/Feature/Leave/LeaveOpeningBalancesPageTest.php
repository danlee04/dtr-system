<?php

namespace Tests\Feature\Leave;

use App\Enums\LedgerKind;
use App\Filament\Pages\LeaveBalances;
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

    public function test_a_balance_already_in_use_stays_open_but_taken_this_year_locks(): void
    {
        $pedro = Employee::factory()->for($this->office)->create();
        $this->openWithCredit();
        // Credits alone do not lock: a first save is accepted at any time.
        LeaveLedgerEntry::factory()->for($pedro)->create(['kind' => LedgerKind::MonthlyCredit, 'posted_on' => '2026-12-01']);

        Livewire::test(LeaveOpeningBalances::class)
            ->assertSeeText('a change is saved as an adjustment')
            ->assertSeeHtml("rows.{$this->juan->id}.VL")
            ->assertDontSeeHtml("rows.{$this->juan->id}.FL")
            ->assertSeeHtml("rows.{$pedro->id}.FL");
    }

    public function test_a_change_to_a_balance_already_in_use_asks_first_then_posts_an_adjustment(): void
    {
        $this->travelTo('2026-11-23 09:00');
        $this->openWithCredit();

        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$this->juan->id}.VL", '12')
            ->mountAction('save')
            ->assertMountedActionModalSee(['1 balance already in use will be corrected with an adjustment dated Nov 23, 2026', 'Dela Cruz, Juan S.: VL 10.000 to 12.000'])
            ->callMountedAction()
            ->assertNotified('Opening balances saved: 1 change')
            ->assertRedirect(LeaveBalances::getUrl());

        $this->assertSame('2.000', $this->juan->leaveLedgerEntries()->where('kind', LedgerKind::Adjustment)->sole()->days);
    }

    public function test_a_save_that_touches_no_balance_in_use_does_not_ask(): void
    {
        $this->openWithCredit();
        $maria = Employee::factory()->for($this->office)->create();

        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$maria->id}.VL", '5')
            ->callAction('save')
            ->assertNotified('Opening balances saved: 1 change');
    }

    /** Juan's VL opening of 10, and a credit after it: the opening is in use. */
    private function openWithCredit(): void
    {
        LeaveLedgerEntry::factory()->for($this->juan)->create(['leave_type' => 'VL', 'kind' => LedgerKind::Opening, 'posting_key' => "opening:{$this->juan->id}:VL", 'days' => '10.000', 'posted_on' => '2026-10-31']);
        LeaveLedgerEntry::factory()->for($this->juan)->create(['leave_type' => 'VL', 'kind' => LedgerKind::MonthlyCredit, 'days' => '1.250', 'posted_on' => '2026-11-01']);
    }

    public function test_hr_opens_the_page_and_sees_the_as_of_date(): void
    {
        $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Balances as of Oct 31, 2026');
    }

    public function test_everyone_who_earns_credits_is_listed_with_their_balances(): void
    {
        LeaveLedgerEntry::factory()->for($this->juan)->create(['leave_type' => 'VL', 'days' => '10', 'kind' => LedgerKind::Opening, 'posting_key' => "opening:{$this->juan->id}:VL"]);
        Employee::factory()->for(Office::factory())->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => null]);

        Livewire::test(LeaveOpeningBalances::class)
            ->assertSet("rows.{$this->juan->id}.VL", '10.000')
            ->assertSee(['Dela Cruz, Juan S.', 'Santos, Maria'])
            // The same number typed differently is no change.
            ->set("rows.{$this->juan->id}.VL", '10')
            ->assertDontSee('unsaved change');
    }

    public function test_the_office_filter_hides_rows_but_keeps_what_was_typed_in_them(): void
    {
        $maria = Employee::factory()->for(Office::factory())->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => null]);

        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$maria->id}.VL", '5')
            ->set('officeId', $this->office->id)
            ->assertSee('Dela Cruz, Juan S.')
            ->assertDontSee('Santos, Maria')
            ->assertSee('1 unsaved change')
            ->callAction('save')
            ->assertNotified('Opening balances saved: 1 change');

        $this->assertSame('5.000', $maria->leaveLedgerEntries()->sole()->days);
    }

    public function test_saving_the_grid_writes_the_rows_and_goes_back_to_balances(): void
    {
        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$this->juan->id}.SL", '7.5')
            ->callAction('save')
            ->assertHasNoErrors()
            ->assertNotified('Opening balances saved: 1 change')
            ->assertRedirect(LeaveBalances::getUrl());

        $this->assertSame('7.500', $this->juan->leaveLedgerEntries()->sole()->days);
    }

    public function test_a_save_with_nothing_changed_stays_on_the_grid(): void
    {
        Livewire::test(LeaveOpeningBalances::class)
            ->callAction('save')
            ->assertNotified('No changes to save')
            ->assertNoRedirect();
    }

    public function test_a_bad_cell_is_marked(): void
    {
        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$this->juan->id}.SL", 'abc')
            ->callAction('save')
            ->assertHasErrors(["rows.{$this->juan->id}.SL"])
            ->assertNoRedirect();

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_the_grid_comes_a_page_at_a_time(): void
    {
        // With Dela Cruz, eleven rows: Ason to Ison fill the first page of ten.
        foreach (range('A', 'J') as $letter) {
            Employee::factory()->for($this->office)->create(['last_name' => "{$letter}son", 'first_name' => 'Pat', 'middle_name' => null]);
        }

        Livewire::test(LeaveOpeningBalances::class)
            ->set('perPage', 10)
            ->assertSee('Ison, Pat')
            ->assertDontSee('Json, Pat')
            ->call('nextPage')
            ->assertSee('Json, Pat');
    }

    public function test_without_a_start_date_the_page_says_so_and_saves_nothing(): void
    {
        config(['leave.start_date' => null]);

        $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Set LEAVE_START_DATE');

        Livewire::test(LeaveOpeningBalances::class)
            ->set("rows.{$this->juan->id}.SL", '7.5')
            ->callAction('save');

        $this->assertSame(0, LeaveLedgerEntry::count());
    }
}
