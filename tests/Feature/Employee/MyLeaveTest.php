<?php

namespace Tests\Feature\Employee;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Employee\Pages\History;
use App\Filament\Employee\Pages\LeaveTypes;
use App\Filament\Employee\Pages\MyDtr;
use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\Leave\LeaveLedger;
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

    public function test_the_history_of_every_type_runs_each_types_own_balance(): void
    {
        $rows = app(LeaveLedger::class)->history($this->employee);

        $this->assertSame([LeaveType::Vacation, LeaveType::Sick, LeaveType::Vacation], array_column($rows, 'leave_type'));
        $this->assertSame(['3.500', '2.000', '5.000'], array_column($rows, 'balance'));
    }

    public function test_the_history_page_lists_every_type_in_one_table_and_filters_by_one(): void
    {
        Livewire::test(History::class)
            ->assertSee(['3.500', '2.000'])
            ->filterTable('leave_type', LeaveType::Sick->value)
            ->assertSee('2.000')
            ->assertDontSee('3.500');
    }

    public function test_the_dashboard_shows_my_balances_and_my_leave_waiting_for_approval(): void
    {
        LeaveApplication::factory()->for($this->employee)->create(['date_from' => '2026-11-23', 'date_to' => '2026-11-27']);
        LeaveApplication::factory()->create(['date_from' => '2026-12-14', 'date_to' => '2026-12-14']);

        $this->get('/')
            ->assertOk()
            ->assertSee(['3.500', '2.000', 'Nov 23–27', 'No DTR uploaded for you yet'])
            ->assertDontSee(['9.000', 'Dec 14']);
    }

    public function test_every_leave_type_is_explained(): void
    {
        foreach (LeaveType::cases() as $type) {
            $this->assertNotSame('', $type->description());
        }

        $this->get(LeaveTypes::getUrl())
            ->assertOk()
            ->assertSee('Wellness Leave')
            ->assertSee('3.500');
    }

    public function test_staff_without_an_employee_see_no_my_leave(): void
    {
        $this->actingAs(User::factory()->create());

        // Staff keep the office's dashboard.
        $this->get('/')->assertOk()->assertDontSee('Leave balance');

        foreach ([History::getUrl(), LeaveTypes::getUrl(), MyDtr::getUrl(), MyLeaveApplicationResource::getUrl('index')] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
