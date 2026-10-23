<?php

namespace Tests\Feature\Leave;

use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearEndPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forced_leave_deducts_the_days_not_taken(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'VL', '8.750');   // + November's 1.250 on Dec 1 = 10.000 on Dec 31
        $this->opening($employee, 'FL', '2.000');

        $this->runPosting('2027-01-01');

        $this->assertSame(['2026-12-31 VL -3.000'], $this->rows($employee, LedgerKind::ForcedLeave));
    }

    public function test_forced_leave_needs_ten_days_of_vl(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'VL', '8.749');   // 9.999 on Dec 31

        $this->runPosting('2027-01-01');

        $this->assertSame([], $this->rows($employee, LedgerKind::ForcedLeave));
    }

    public function test_five_days_taken_means_no_forced_leave(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'VL', '20.000');
        $this->opening($employee, 'FL', '5.000');

        $this->runPosting('2027-01-01');

        $this->assertSame([], $this->rows($employee, LedgerKind::ForcedLeave));
    }

    public function test_unused_spl_and_wellness_lapse_on_31_december(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'SPL', '2.000');
        $this->opening($employee, 'WL', '0.000');

        $this->runPosting('2027-01-01');

        $this->assertSame(['2026-12-31 SPL -2.000'], $this->rows($employee, LedgerKind::Lapse));
    }

    public function test_1_january_grants_spl_and_wellness(): void
    {
        $employee = Employee::factory()->create();

        $this->runPosting('2027-01-01');

        $this->assertSame(['2027-01-01 SPL 3.000', '2027-01-01 WL 5.000'], $this->rows($employee, LedgerKind::YearlyGrant));
        $this->assertSame(['VL' => '2.500', 'SL' => '2.500', 'SPL' => '3.000', 'WL' => '5.000'], app(LeaveLedger::class)->balances($employee));
    }

    public function test_the_start_year_has_no_grant(): void
    {
        $employee = Employee::factory()->create();

        $this->runPosting('2028-01-01', start: '2027-01-01');

        $this->assertSame(['2028-01-01 SPL 3.000', '2028-01-01 WL 5.000'], $this->rows($employee, LedgerKind::YearlyGrant));
    }

    public function test_31_december_is_not_closed_until_it_is_over(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'SPL', '2.000');

        $this->runPosting('2026-12-31');

        $this->assertSame([], $this->rows($employee, LedgerKind::Lapse));
    }

    public function test_a_late_run_reads_the_balance_as_of_31_december(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'VL', '8.750');
        // Read as of 3 January, VL would be 8.750 + 1.250 + 1.250 - 2.000 = 9.250: no forced leave.
        LeaveLedgerEntry::factory()->for($employee)->create(['leave_type' => 'VL', 'days' => '-2.000', 'posted_on' => '2027-01-02']);

        $this->runPosting('2027-01-03');

        $this->assertSame(['2026-12-31 VL -5.000'], $this->rows($employee, LedgerKind::ForcedLeave));
    }

    public function test_a_second_run_posts_no_year_end_rows(): void
    {
        $employee = Employee::factory()->create();
        $this->opening($employee, 'VL', '20.000');
        $this->opening($employee, 'SPL', '1.000');
        $this->runPosting('2027-01-01');

        $this->assertSame(0, $this->runPosting('2027-01-01'));
    }

    public function test_a_closed_year_is_not_posted_again_for_an_employee_added_later(): void
    {
        Employee::factory()->create();
        $this->runPosting('2027-01-01');

        $added = Employee::factory()->create();
        $this->runPosting('2027-01-02');

        $this->assertSame([], $this->rows($added, LedgerKind::YearlyGrant));
    }

    private function runPosting(string $today, string $start = '2026-11-01'): int
    {
        return app(LeavePosting::class)->post(CarbonImmutable::parse($start), CarbonImmutable::parse($today));
    }

    private function opening(Employee $employee, string $type, string $days): void
    {
        LeaveLedgerEntry::factory()->for($employee)->create([
            'leave_type' => $type,
            'days' => $days,
            'kind' => LedgerKind::Opening,
            'posted_on' => '2026-10-31',
            'posting_key' => "opening:{$employee->id}:{$type}",
        ]);
    }

    /** @return list<string> "{posted_on} {type} {days}" */
    private function rows(Employee $employee, LedgerKind $kind): array
    {
        return $employee->leaveLedgerEntries()
            ->where('kind', $kind)
            ->orderBy('posted_on')
            ->orderBy('leave_type')
            ->get()
            ->map(fn (LeaveLedgerEntry $row): string => "{$row->posted_on->toDateString()} {$row->leave_type->value} {$row->days}")
            ->all();
    }
}
