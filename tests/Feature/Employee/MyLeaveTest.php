<?php

namespace Tests\Feature\Employee;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Employee\Pages\History;
use App\Filament\Employee\Pages\LeaveTypes;
use App\Filament\Employee\Widgets\MyLeaveBalances;
use App\Filament\Employee\Widgets\MyPendingApplications;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\Leave\LeaveLedger;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyLeaveTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('employee'));

        $user = User::factory()->employee()->create();
        $this->employee = $user->employee;

        foreach (['VL' => '5.000', 'SL' => '2.000'] as $type => $days) {
            LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => $type, 'days' => $days, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        }
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '-1.500', 'kind' => LedgerKind::Adjustment, 'posted_on' => '2026-11-10']);

        LeaveLedgerEntry::factory()->for(Employee::factory()->create())->create(['leave_type' => 'VL', 'days' => '9.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);

        $this->actingAs($user);
    }

    public function test_the_history_runs_a_balance_newest_first(): void
    {
        $rows = app(LeaveLedger::class)->history($this->employee, LeaveType::Vacation);

        $this->assertSame(['3.500', '5.000'], array_column($rows, 'balance'));
        $this->assertSame(['-1.500', '5.000'], array_column($rows, 'days'));
    }

    public function test_my_leave_shows_only_my_balances(): void
    {
        Livewire::test(MyLeaveBalances::class)
            ->assertSee('3.500')
            ->assertSee('2.000')
            ->assertDontSee('9.000');
    }

    public function test_my_pending_applications_are_mine_only(): void
    {
        $mine = LeaveApplication::factory()->for($this->employee)->create();
        $theirs = LeaveApplication::factory()->create();

        Livewire::test(MyPendingApplications::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_history_page_switches_type(): void
    {
        // FL has no balance of its own, so it falls back to VL.
        Livewire::test(History::class)
            ->assertSee('3.500')
            ->set('type', 'SL')
            ->assertSee('2.000')
            ->assertDontSee('3.500')
            ->set('type', 'FL')
            ->assertSee('3.500');
    }

    public function test_every_leave_type_is_explained(): void
    {
        foreach (LeaveType::cases() as $type) {
            $this->assertNotSame('', $type->description());
        }

        $this->get(LeaveTypes::getUrl(panel: 'employee'))
            ->assertOk()
            ->assertSee('Wellness Leave')
            ->assertSee('3.500');
    }
}
