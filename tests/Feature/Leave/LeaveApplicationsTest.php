<?php

namespace Tests\Feature\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveDetail;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use App\Services\Leave\LeaveLedger;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-11-02 08:00');
        $this->actingAs(User::factory()->create());

        $this->employee = Employee::factory()->create(['last_name' => 'Dela Cruz']);
        ScheduleAssignment::factory()->for($this->employee)->for(Schedule::factory()->create(['code' => 'REG']))->create(['effective_from' => '2026-11-01']);

        foreach (['VL' => '5.000', 'SL' => '2.000'] as $type => $days) {
            LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => $type, 'days' => $days, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        }
    }

    public function test_filing_deducts_the_balance_at_once(): void
    {
        $application = $this->fileLeave();

        $this->assertSame(LeaveApplicationStatus::Pending, $application->status);
        $this->assertSame(['2.000', '2.000', '0.000'], [$application->days, $application->days_with_pay, $application->days_without_pay]);
        $this->assertSame('3.000', $this->balances()['VL']);
        $this->assertSame(auth()->id(), $application->filed_by_user_id);
        $this->assertSame('-2.000', $application->ledgerEntries()->sole()->days);
    }

    public function test_sick_leave_runs_into_vl_then_goes_without_pay(): void
    {
        // Nov 3–13 has 9 weekdays: SL 2 + VL 5 with pay, 2 without.
        $application = $this->fileLeave(['leave_type' => 'SL', 'date_to' => '2026-11-13']);

        $this->assertSame(['9.000', '7.000', '2.000'], [$application->days, $application->days_with_pay, $application->days_without_pay]);
        $this->assertSame(['VL' => '0.000', 'SL' => '0.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
        $this->assertSame('1.000', $application->breakdown['2026-11-13']['without_pay']);
    }

    public function test_approving_records_who_and_when(): void
    {
        $application = $this->fileLeave();

        app(LeaveApplications::class)->approve($application);

        $this->assertSame(LeaveApplicationStatus::Approved, $application->fresh()->status);
        $this->assertSame(auth()->id(), $application->fresh()->approved_by_user_id);
        $this->assertNotNull($application->fresh()->approved_at);
        $this->assertSame('3.000', $this->balances()['VL']);
    }

    public function test_cancelling_gives_back_exactly_what_was_drawn(): void
    {
        $application = $this->fileLeave(['leave_type' => 'SL', 'date_to' => '2026-11-13']);

        app(LeaveApplications::class)->cancel($application, 'Disapproved by the Chief of Hospital');

        $this->assertSame(['VL' => '5.000', 'SL' => '2.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
        $this->assertSame('Disapproved by the Chief of Hospital', $application->fresh()->cancel_reason);
        $this->assertSame(auth()->id(), $application->fresh()->cancelled_by_user_id);
        $this->assertSame(2, $application->ledgerEntries()->where('kind', LedgerKind::Cancellation)->count());
    }

    public function test_a_cancelled_application_cannot_be_cancelled_or_approved_again(): void
    {
        $application = $this->fileLeave();
        app(LeaveApplications::class)->cancel($application, 'Withdrawn');

        $this->assertRefused(fn () => app(LeaveApplications::class)->cancel($application->fresh(), 'Withdrawn'), 'status');
        $this->assertRefused(fn () => app(LeaveApplications::class)->approve($application->fresh()), 'status');
        $this->assertSame('5.000', $this->balances()['VL']);
    }

    public function test_an_approval_from_a_stale_screen_cannot_undo_a_cancellation(): void
    {
        $application = $this->fileLeave();
        $stale = LeaveApplication::find($application->id);   // the other HR user's screen, still pending

        app(LeaveApplications::class)->cancel($application, 'Disapproved');

        $this->assertRefused(fn () => app(LeaveApplications::class)->approve($stale), 'status');
        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
        $this->assertSame('5.000', $this->balances()['VL']);
    }

    public function test_leave_of_a_deleted_employee_can_still_be_cancelled(): void
    {
        $application = $this->fileLeave();
        $this->employee->delete();

        app(LeaveApplications::class)->cancel($application->fresh(), 'Separated before the leave');

        $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
        $this->assertSame('Dela Cruz', $application->fresh()->employee->last_name);
        $this->assertSame('5.000', $this->balances()['VL']);
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $application = $this->fileLeave();

        $this->assertRefused(fn () => app(LeaveApplications::class)->cancel($application, ' '), 'cancel_reason');
    }

    public function test_overlapping_dates_are_refused_but_two_halves_are_not(): void
    {
        $first = $this->fileLeave();

        $this->assertRefused(fn () => $this->fileLeave(['date_from' => '2026-11-04', 'date_to' => '2026-11-04']), 'date_from');

        $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'coverage' => 'first_half']);
        $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'coverage' => 'second_half']);

        app(LeaveApplications::class)->cancel($first, 'Wrong dates');
        $this->fileLeave();
        $this->assertSame(4, LeaveApplication::count());
    }

    public function test_an_employee_who_earns_no_credits_cannot_have_leave_filed(): void
    {
        $this->employee->update(['employment_status' => EmploymentStatus::JobOrder]);

        $this->assertRefused(fn () => $this->fileLeave(), 'employee_id');
    }

    public function test_filing_without_a_start_date_is_refused(): void
    {
        config(['leave.start_date' => null]);

        $this->assertRefused(fn () => $this->fileLeave(), 'date_from');
        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_details_follow_the_leave_form(): void
    {
        $this->assertRefused(fn () => $this->fileLeave(['detail' => 'in_hospital']), 'detail');
        $this->assertRefused(fn () => $this->fileLeave(['detail' => 'abroad']), 'detail_text');

        $application = $this->fileLeave(['detail' => 'abroad', 'detail_text' => 'Japan', 'commutation_requested' => true]);
        $this->assertSame([LeaveDetail::Abroad, 'Japan', true], [$application->detail, $application->detail_text, $application->commutation_requested]);
    }

    public function test_spl_that_has_lapsed_is_not_given_back(): void
    {
        LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'SPL', 'days' => '3.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        $this->travelTo('2026-12-01 08:00');
        $application = $this->fileLeave(['leave_type' => 'SPL', 'date_from' => '2026-12-28', 'date_to' => '2026-12-28']);

        $this->travelTo('2027-01-04 08:00');
        app(LeaveApplications::class)->cancel($application, 'Did not go');

        $this->assertSame(0, $application->ledgerEntries()->where('kind', LedgerKind::Cancellation)->count());
        $this->assertSame('3.000', $this->balances()['SPL']);   // 2027's grant only
    }

    /** @param  array<string, mixed>  $overrides */
    private function fileLeave(array $overrides = []): LeaveApplication
    {
        return app(LeaveApplications::class)->file($this->employee->fresh(), array_merge([
            'leave_type' => 'VL',
            'date_from' => '2026-11-03',
            'date_to' => '2026-11-04',
            'coverage' => 'whole_day',
        ], $overrides));
    }

    /** @return array<string, string> */
    private function balances(): array
    {
        return app(LeaveLedger::class)->balances($this->employee);
    }

    private function assertRefused(Closure $call, string $errorKey): void
    {
        try {
            $call();
            $this->fail("Nothing was refused on {$errorKey}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
    }
}
