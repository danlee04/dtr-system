<?php

namespace Tests\Feature\Leave;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Pages\LeaveBalances;
use App\Filament\Pages\LeaveOpeningBalances;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveBalancesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-20 09:00');
        config(['leave.start_date' => '2026-10-01']);
    }

    private function row(Employee $employee, LeaveType $type, string $days, LedgerKind $kind, string $postedOn, ?string $period = null, ?LeaveApplication $application = null): void
    {
        LeaveLedgerEntry::factory()->for($employee)->create([
            'leave_type' => $type, 'days' => $days, 'kind' => $kind, 'posted_on' => $postedOn, 'period' => $period, 'leave_application_id' => $application?->id,
        ]);
    }

    public function test_each_balance_is_the_months_end_with_what_the_month_took_in_red(): void
    {
        $this->actingAs(User::factory()->create());
        $ana = Employee::factory()->create(['last_name' => 'Abad']);
        $this->row($ana, LeaveType::Vacation, '5.000', LedgerKind::Opening, '2026-09-30');
        $this->row($ana, LeaveType::Sick, '3.000', LedgerKind::Opening, '2026-09-30');
        $this->row($ana, LeaveType::SpecialPrivilege, '3.000', LedgerKind::Opening, '2026-09-30');
        // October's credit, posted November 1, and October's DTR, marked final November 20: both October's.
        $this->row($ana, LeaveType::Vacation, '1.250', LedgerKind::MonthlyCredit, '2026-11-01', '2026-10');
        $this->row($ana, LeaveType::Sick, '1.250', LedgerKind::MonthlyCredit, '2026-11-01', '2026-10');
        $this->row($ana, LeaveType::Vacation, '-0.100', LedgerKind::DtrDeduction, '2026-11-20', '2026-10');
        // SPL for October 15, filed November 2: October's too, and shown under SPL.
        $spl = LeaveApplication::factory()->for($ana)->create(['leave_type' => LeaveType::SpecialPrivilege, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15']);
        $this->row($ana, LeaveType::SpecialPrivilege, '-1.000', LedgerKind::Application, '2026-11-02', application: $spl);

        Livewire::test(LeaveBalances::class)
            ->assertSet('month', '2026-10')
            ->assertSeeInOrder(['Abad', '6.150', "\u{2212}0.100", '4.250', '2.000', "\u{2212}1.000", '0.000'])
            // November took nothing yet: the same balances, no red.
            ->set('month', '2026-11')
            ->assertSeeInOrder(['Abad', '6.150', '4.250', '2.000'])
            ->assertDontSee("\u{2212}0.100");
    }

    public function test_a_reopened_dtr_or_a_cancelled_leave_takes_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $ana = Employee::factory()->create();
        $this->row($ana, LeaveType::Vacation, '5.000', LedgerKind::Opening, '2026-09-30');
        $this->row($ana, LeaveType::Vacation, '-0.100', LedgerKind::DtrDeduction, '2026-11-20', '2026-10');
        $this->row($ana, LeaveType::Vacation, '0.100', LedgerKind::DtrReopened, '2026-11-20', '2026-10');
        $leave = LeaveApplication::factory()->for($ana)->create(['date_from' => '2026-10-20', 'date_to' => '2026-10-20']);
        $this->row($ana, LeaveType::Vacation, '-1.000', LedgerKind::Application, '2026-10-19', application: $leave);
        $this->row($ana, LeaveType::Vacation, '1.000', LedgerKind::Cancellation, '2026-11-05', application: $leave);

        Livewire::test(LeaveBalances::class)->assertSee('5.000')->assertDontSee("\u{2212}");
    }

    public function test_employees_cannot_open_the_balances(): void
    {
        $this->actingAs(User::factory()->employee(Employee::factory()->create())->create());

        $this->get(LeaveBalances::getUrl())->assertForbidden();
    }

    public function test_opening_balances_are_entered_from_the_balances_not_the_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(LeaveBalances::class)->assertActionHasUrl('openingBalances', LeaveOpeningBalances::getUrl());
        $this->assertFalse(LeaveOpeningBalances::shouldRegisterNavigation());
    }
}
