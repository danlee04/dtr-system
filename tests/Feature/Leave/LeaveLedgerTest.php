<?php

namespace Tests\Feature\Leave;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\Leave\LeaveLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LeaveLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create();
    }

    public function test_a_balance_is_the_sum_of_its_rows(): void
    {
        $this->row('VL', '10.000', '2026-10-31');
        $this->row('VL', '1.250', '2026-12-01');
        $this->row('SL', '-0.500', '2026-12-03');

        $this->assertSame(['VL' => '11.250', 'SL' => '-0.500', 'SPL' => '0.000', 'WL' => '0.000'], app(LeaveLedger::class)->balances($this->employee));
    }

    public function test_a_balance_on_a_date_ignores_later_rows(): void
    {
        $this->row('VL', '10.000', '2026-10-31');
        $this->row('VL', '1.250', '2026-12-01');

        $this->assertSame('10.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation, CarbonImmutable::parse('2026-11-30')));
    }

    public function test_hr_adjusts_a_balance_with_a_reason(): void
    {
        $this->travelTo('2026-11-20 09:00');

        $entry = app(LeaveLedger::class)->adjust($this->employee, LeaveType::Vacation, '2.5', 'Credits from the old leave card');

        $this->assertSame('2.500', $entry->days);
        $this->assertSame('2026-11-20', $entry->posted_on->toDateString());
        $this->assertSame(LedgerKind::Adjustment, $entry->kind);
        $this->assertSame('Credits from the old leave card', $entry->remarks);
        $this->assertSame(auth()->id(), $entry->created_by_user_id);
        $this->assertSame(auth()->id(), Activity::query()->whereMorphedTo('subject', $entry)->sole()->causer_id);
    }

    public function test_an_adjustment_cannot_take_a_balance_below_zero(): void
    {
        $this->row('VL', '1.000', '2026-10-31');

        try {
            app(LeaveLedger::class)->adjust($this->employee, LeaveType::Vacation, '-1.500', 'Tardiness, October');
            $this->fail('The adjustment was saved.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('-0.500', $exception->errors()['days'][0]);
        }

        $this->assertSame('1.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
    }

    public function test_an_adjustment_checks_the_balance_after_posting_what_is_due(): void
    {
        config(['leave.start_date' => '2026-11-01']);
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '8.750', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        // Not yet posted: +1.250 on Dec 1, forced leave -5.000 on Dec 31, +1.250 on Jan 1 = 6.250.
        $this->travelTo('2027-01-02 07:00');

        try {
            app(LeaveLedger::class)->adjust($this->employee, LeaveType::Vacation, '-8.000', 'Tardiness, December');
            $this->fail('The adjustment was saved against a stale balance.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('-1.750', $exception->errors()['days'][0]);
        }
    }

    public function test_an_adjustment_of_zero_or_of_an_untracked_type_is_refused(): void
    {
        $this->assertAdjustRefused(LeaveType::Vacation, '0', 'days');
        $this->assertAdjustRefused(LeaveType::Vacation, '1.2505', 'days');
        $this->assertAdjustRefused(LeaveType::Vacation, "1.5\n", 'days');
        $this->assertAdjustRefused(LeaveType::Forced, '1.000', 'leave_type');
        $this->assertAdjustRefused(LeaveType::Maternity, '1.000', 'leave_type');

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_ledger_rows_cannot_be_edited_or_deleted(): void
    {
        $entry = $this->row('VL', '1.250', '2026-12-01');

        $this->assertTrue(auth()->user()->can('view', $entry));
        $this->assertFalse(auth()->user()->can('update', $entry));
        $this->assertFalse(auth()->user()->can('delete', $entry));
    }

    private function row(string $type, string $days, string $postedOn): LeaveLedgerEntry
    {
        return LeaveLedgerEntry::factory()->for($this->employee)->create([
            'leave_type' => $type,
            'days' => $days,
            'posted_on' => $postedOn,
        ]);
    }

    private function assertAdjustRefused(LeaveType $type, string $days, string $errorKey): void
    {
        try {
            app(LeaveLedger::class)->adjust($this->employee, $type, $days, 'A reason');
            $this->fail("{$type->value} {$days} was saved.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
    }
}
