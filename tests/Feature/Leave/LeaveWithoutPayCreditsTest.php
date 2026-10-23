<?php

namespace Tests\Feature\Leave;

use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use App\Services\Leave\LeavePosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveWithoutPayCreditsTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');
        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create();
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);
    }

    public function test_days_without_pay_reduce_the_months_credit(): void
    {
        $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03']);   // nothing on hand: 1 day without pay

        $this->travelTo('2026-12-01 08:00');
        app(LeavePosting::class)->catchUp();

        $this->assertSame(['monthly_credit 1.208'], $this->creditRows('2026-11'));
    }

    public function test_without_pay_filed_into_a_credited_month_adds_a_correction(): void
    {
        $this->travelTo('2026-12-02 08:00');
        // Posts November (1.250), then 5 days with VL 1.250 on hand: 3.750 without pay.
        $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

        // 1.25 × (30 − 3.75) ÷ 30 = 1.094, less the 1.250 posted.
        $this->assertSame(['monthly_credit 1.250', 'credit_correction -0.156'], $this->creditRows('2026-11'));
        $this->assertSame('-0.156', $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::CreditCorrection)->where('leave_type', 'SL')->sole()->days);
    }

    public function test_cancelling_without_pay_reverses_the_correction(): void
    {
        $this->travelTo('2026-12-02 08:00');
        $application = $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

        app(LeaveApplications::class)->cancel($application, 'Withdrawn');

        $this->assertSame(['monthly_credit 1.250', 'credit_correction -0.156', 'credit_correction 0.156'], $this->creditRows('2026-11'));
    }

    public function test_a_correction_carries_only_the_leave_without_pay(): void
    {
        $this->travelTo('2026-12-02 08:00');
        app(LeavePosting::class)->catchUp();   // November: 1.250, no hire date on file
        $this->employee->update(['date_hired' => '2026-11-15']);   // filled in later: not a re-post

        // VL 1.250 on hand covers Nov 27 and a quarter of Nov 30: 0.750 without pay.
        $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-27', 'date_to' => '2026-11-30']);

        // On 16 days of service that costs 0.667 − 0.635 = 0.032; the late hire date costs nothing here.
        $this->assertSame(['monthly_credit 1.250', 'credit_correction -0.032'], $this->creditRows('2026-11'));
    }

    public function test_a_month_the_employee_was_not_credited_gets_no_correction(): void
    {
        // Someone else is credited, so November counts as posted for the watermark.
        Employee::factory()->create();
        $this->employee->update(['is_active' => false]);
        $this->travelTo('2026-12-02 08:00');
        app(LeavePosting::class)->catchUp();
        $this->employee->update(['is_active' => true]);

        $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

        $this->assertSame([], $this->creditRows('2026-11'));
    }

    public function test_vl_and_fl_applications_count_toward_the_five_days(): void
    {
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '20.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);
        $this->fileLeave(['leave_type' => 'FL', 'date_from' => '2026-11-09', 'date_to' => '2026-11-09']);

        $this->travelTo('2027-01-01 08:00');
        app(LeavePosting::class)->catchUp();

        $this->assertSame('-1.000', $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::ForcedLeave)->sole()->days);
    }

    public function test_a_cancelled_application_does_not_count_toward_the_five_days(): void
    {
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '20.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        $application = $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);
        app(LeaveApplications::class)->cancel($application, 'Withdrawn');

        $this->travelTo('2027-01-01 08:00');
        app(LeavePosting::class)->catchUp();

        $this->assertSame('-5.000', $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::ForcedLeave)->sole()->days);
    }

    /** @param  array<string, mixed>  $data */
    private function fileLeave(array $data): LeaveApplication
    {
        return app(LeaveApplications::class)->file($this->employee->fresh(), $data + ['coverage' => 'whole_day']);
    }

    /** @return list<string> the employee's VL credit rows for a month, "{kind} {days}" in id order */
    private function creditRows(string $period): array
    {
        return $this->employee->leaveLedgerEntries()
            ->where('leave_type', 'VL')
            ->where('period', $period)
            ->whereIn('kind', [LedgerKind::MonthlyCredit, LedgerKind::CreditCorrection])
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveLedgerEntry $row): string => "{$row->kind->value} {$row->days}")
            ->all();
    }
}
