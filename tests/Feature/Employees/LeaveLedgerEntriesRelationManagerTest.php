<?php

namespace Tests\Feature\Employees;

use App\Enums\LedgerKind;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\LeaveLedgerEntriesRelationManager;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveLedgerEntriesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create();
    }

    private function manager(): Testable
    {
        return Livewire::test(LeaveLedgerEntriesRelationManager::class, [
            'ownerRecord' => $this->employee,
            'pageClass' => EditEmployee::class,
        ]);
    }

    public function test_the_tab_shows_the_balances_and_the_rows(): void
    {
        $row = LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '10.000', 'kind' => LedgerKind::Opening]);

        $this->manager()
            ->assertSee('VL 10.000 · SL 0.000 · SPL 0.000 · Wellness 0.000')
            ->assertCanSeeTableRecords([$row]);
    }

    public function test_hr_adds_an_adjustment(): void
    {
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'SL', 'days' => '2.000', 'kind' => LedgerKind::Opening]);

        $this->manager()
            ->callAction(TestAction::make('adjust')->table(), ['leave_type' => 'SL', 'days' => '-0.5', 'remarks' => 'Undertime, October'])
            ->assertHasNoFormErrors();

        $row = $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::Adjustment)->sole();
        $this->assertSame('-0.500', $row->days);
        $this->assertSame('Undertime, October', $row->remarks);
    }

    public function test_an_adjustment_below_zero_is_refused_on_the_form(): void
    {
        $this->manager()
            ->callAction(TestAction::make('adjust')->table(), ['leave_type' => 'SL', 'days' => '-0.5', 'remarks' => 'Undertime'])
            ->assertHasFormErrors(['days']);

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_days_that_are_not_a_number_are_refused_on_the_form(): void
    {
        foreach (['abc', '1.5 ', '1,5'] as $typed) {
            $this->manager()
                ->callAction(TestAction::make('adjust')->table(), ['leave_type' => 'SL', 'days' => $typed, 'remarks' => 'Typo'])
                ->assertHasFormErrors(['days']);
        }

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_rows_have_no_edit_or_delete_action(): void
    {
        $row = LeaveLedgerEntry::factory()->for($this->employee)->create();

        $this->manager()
            ->assertActionDoesNotExist(TestAction::make('edit')->table($row))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($row));
    }
}
