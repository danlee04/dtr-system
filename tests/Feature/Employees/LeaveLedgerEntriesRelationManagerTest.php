<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\LedgerKind;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\RelationManagers\LeaveLedgerEntriesRelationManager;
use App\Filament\Resources\Employees\Widgets\EmployeeLeaveBalances;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

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
            'pageClass' => ViewEmployee::class,
        ]);
    }

    public function test_the_tab_shows_the_rows_and_the_page_the_balances(): void
    {
        $row = LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '10.000', 'kind' => LedgerKind::Opening]);

        $this->manager()->assertCanSeeTableRecords([$row]);

        Livewire::test(EmployeeLeaveBalances::class, ['record' => $this->employee])
            ->assertSee(['Vacation Leave', '10.000', 'Wellness Leave', '0.000']);
    }

    public function test_the_balances_show_only_for_those_who_earn_leave(): void
    {
        $page = fn (Employee $employee) => Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])->instance();

        $this->assertSame([EmployeeLeaveBalances::class], invade($page($this->employee))->getHeaderWidgets());
        $this->assertSame([], invade($page(Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder])))->getHeaderWidgets());
    }

    public function test_hr_files_leave_from_the_tab_for_this_employee(): void
    {
        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '5.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);

        $this->manager()
            ->callAction(TestAction::make('fileLeave')->table(), ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03'])
            ->assertHasNoFormErrors();

        $this->assertTrue(LeaveApplication::sole()->employee->is($this->employee));
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
