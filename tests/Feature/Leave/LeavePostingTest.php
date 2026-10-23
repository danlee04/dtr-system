<?php

namespace Tests\Feature\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LeavePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_credit_follows_the_csc_table(): void
    {
        $this->assertSame('1.250', LeavePosting::creditFor(30));
        $this->assertSame('1.208', LeavePosting::creditFor(30, '1.000'));
        $this->assertSame('1.188', LeavePosting::creditFor(30, '1.500'));
        $this->assertSame('1.224', LeavePosting::creditFor(30, '0.625'));
        $this->assertSame('1.000', LeavePosting::creditFor(30, '6.000'));
        $this->assertSame('0.667', LeavePosting::creditFor(16));
        $this->assertSame('0.417', LeavePosting::creditFor(10));
        $this->assertSame('0.000', LeavePosting::creditFor(30, '30.000'));
    }

    public function test_the_first_of_the_month_credits_the_month_just_ended(): void
    {
        $employee = Employee::factory()->create();

        $this->assertSame(2, $this->runPosting('2026-12-01'));
        $this->assertSame(['2026-12-01 SL 1.250', '2026-12-01 VL 1.250'], $this->credits($employee));
        $this->assertSame('2026-11', $employee->leaveLedgerEntries()->first()->period);
    }

    public function test_nothing_is_due_before_the_month_has_ended(): void
    {
        Employee::factory()->create();

        $this->assertSame(0, $this->runPosting('2026-11-30'));
    }

    public function test_a_second_run_posts_nothing(): void
    {
        Employee::factory()->create();
        $this->runPosting('2026-12-01');

        $this->assertSame(0, $this->runPosting('2026-12-01'));
    }

    public function test_missed_months_are_caught_up_with_their_own_dates(): void
    {
        $employee = Employee::factory()->create();

        $this->runPosting('2027-03-05');

        $this->assertSame(
            ['2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01'],
            $employee->leaveLedgerEntries()
                ->where('leave_type', 'VL')
                ->orderBy('posted_on')
                ->get()
                ->map(fn (LeaveLedgerEntry $row): string => $row->posted_on->toDateString())
                ->all(),
        );
    }

    public function test_a_month_of_hiring_or_separation_is_prorated(): void
    {
        $hired = Employee::factory()->create(['date_hired' => '2026-11-15']);
        $separated = Employee::factory()->create(['date_separated' => '2026-11-10']);

        $this->runPosting('2026-12-01');

        $this->assertSame(['2026-12-01 SL 0.667', '2026-12-01 VL 0.667'], $this->credits($hired));
        $this->assertSame(['2026-12-01 SL 0.417', '2026-12-01 VL 0.417'], $this->credits($separated));
    }

    public function test_no_row_for_a_month_the_employee_did_not_serve(): void
    {
        $later = Employee::factory()->create(['date_hired' => '2026-12-02']);
        $gone = Employee::factory()->create(['date_separated' => '2026-10-31']);

        $this->runPosting('2026-12-01');

        $this->assertSame([], $this->credits($later));
        $this->assertSame([], $this->credits($gone));
    }

    public function test_only_employees_who_earn_credits_are_credited(): void
    {
        Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);
        Employee::factory()->create(['employment_status' => EmploymentStatus::ContractOfService]);
        Employee::factory()->create(['is_active' => false]);
        tap(Employee::factory()->create())->delete();
        $coterminous = Employee::factory()->create(['employment_status' => EmploymentStatus::Coterminous]);

        $this->runPosting('2026-12-01');

        $this->assertSame(2, LeaveLedgerEntry::count());
        $this->assertCount(2, $this->credits($coterminous));
    }

    public function test_a_posted_month_is_not_posted_again_after_a_later_change(): void
    {
        Employee::factory()->create();
        $converted = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);
        $reactivated = Employee::factory()->create(['is_active' => false]);
        $this->runPosting('2026-12-01');

        $converted->update(['employment_status' => EmploymentStatus::Permanent]);
        $reactivated->update(['is_active' => true]);
        $added = Employee::factory()->create(['date_hired' => null]);
        $this->runPosting('2027-01-01');

        foreach ([$converted, $reactivated, $added] as $employee) {
            $this->assertSame(
                ['2027-01-01 SL 1.250', '2027-01-01 VL 1.250'],
                $this->credits($employee, LedgerKind::MonthlyCredit),
                "{$employee->full_name} was credited for November.",
            );
        }
    }

    public function test_posted_rows_stay_out_of_the_activity_log(): void
    {
        Employee::factory()->create();
        $before = Activity::count();

        $this->runPosting('2026-12-01');

        $this->assertSame($before, Activity::count());
    }

    private function runPosting(string $today): int
    {
        return app(LeavePosting::class)->post(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse($today));
    }

    /** @return list<string> "{posted_on} {type} {days}" */
    private function credits(Employee $employee, ?LedgerKind $kind = null): array
    {
        return $employee->leaveLedgerEntries()
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->orderBy('posted_on')
            ->orderBy('leave_type')
            ->get()
            ->map(fn (LeaveLedgerEntry $row): string => "{$row->posted_on->toDateString()} {$row->leave_type->value} {$row->days}")
            ->all();
    }
}
