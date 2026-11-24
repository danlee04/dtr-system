<?php

namespace Tests\Feature\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveDetail;
use App\Enums\LeaveType;
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

    public function test_a_pending_application_is_changed_in_place_without_extra_ledger_rows(): void
    {
        $application = $this->fileLeave();

        $this->travelTo('2026-11-03 09:00');
        app(LeaveApplications::class)->update($application, ['leave_type' => 'SL', 'date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'coverage' => 'whole_day', 'division_head' => 'Dr. Reyes']);

        $application->refresh();
        $this->assertSame([LeaveType::Sick, '2026-11-05', '1.000', 'Dr. Reyes'], [$application->leave_type, $application->date_from->toDateString(), $application->days, $application->division_head]);
        // What it draws now, still dated the day it was filed (HR, 2026-11-23).
        $row = $application->ledgerEntries()->sole();
        $this->assertSame(['SL', '-1.000', '2026-11-02', LedgerKind::Application], [$row->leave_type->value, $row->days, $row->posted_on->toDateString(), $row->kind]);
        $this->assertSame(['VL' => '5.000', 'SL' => '1.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
        $this->assertSame(['VL' => '5.000', 'SL' => '2.000'], $application->credits_at_filing);
    }

    public function test_only_a_pending_application_can_be_changed(): void
    {
        $approved = $this->fileLeave();
        app(LeaveApplications::class)->approve($approved);
        $cancelled = $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05']);
        app(LeaveApplications::class)->cancel($cancelled, 'Withdrawn');

        foreach ([$approved, $cancelled] as $application) {
            $this->assertRefused(fn () => app(LeaveApplications::class)->update($application->fresh(), ['leave_type' => 'VL', 'date_from' => '2026-11-06', 'date_to' => '2026-11-06']), 'status');
        }
    }

    public function test_a_change_keeps_its_own_dates_but_may_not_overlap_another(): void
    {
        $application = $this->fileLeave();
        $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05']);

        app(LeaveApplications::class)->update($application, ['leave_type' => 'SL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-04']);

        $this->assertRefused(fn () => app(LeaveApplications::class)->update($application->fresh(), ['leave_type' => 'VL', 'date_from' => '2026-11-04', 'date_to' => '2026-11-05']), 'date_from');
        $this->assertSame(LeaveType::Sick, $application->fresh()->leave_type);
    }

    public function test_disapproving_gives_the_days_back_with_the_reason(): void
    {
        $application = $this->fileLeave(['leave_type' => 'SL', 'date_to' => '2026-11-13']);

        app(LeaveApplications::class)->disapprove($application, ' Short of staff that week ');

        $application->refresh();
        $this->assertSame([LeaveApplicationStatus::Disapproved, 'Short of staff that week', auth()->id()], [$application->status, $application->disapproval_reason, $application->disapproved_by_user_id]);
        $this->assertNotNull($application->disapproved_at);
        $this->assertSame(['VL' => '5.000', 'SL' => '2.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
        $this->assertSame("#{$application->id} disapproved: Short of staff that week", $application->ledgerEntries()->where('kind', LedgerKind::Cancellation)->first()->remarks);

        // Closed like a cancelled one, and its dates are free again.
        $this->assertRefused(fn () => app(LeaveApplications::class)->disapprove($application->fresh(), 'Again'), 'status');
        $this->assertRefused(fn () => app(LeaveApplications::class)->cancel($application->fresh(), 'Withdrawn'), 'status');
        $this->assertRefused(fn () => app(LeaveApplications::class)->approve($application->fresh()), 'status');
        $this->fileLeave();
    }

    public function test_only_a_pending_application_with_a_reason_is_disapproved(): void
    {
        $application = $this->fileLeave();
        $this->assertRefused(fn () => app(LeaveApplications::class)->disapprove($application, ' '), 'disapproval_reason');

        app(LeaveApplications::class)->approve($application);
        $this->assertRefused(fn () => app(LeaveApplications::class)->disapprove($application->fresh(), 'Too late'), 'status');
        $this->assertSame('3.000', $this->balances()['VL']);
    }

    public function test_the_employee_is_told_in_the_app_when_hr_approves_disapproves_or_cancels(): void
    {
        $account = User::factory()->employee($this->employee)->create();
        $leave = app(LeaveApplications::class);

        $leave->approve($this->fileLeave());
        $leave->disapprove($this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05']), 'Short of staff <b>that</b> week');
        $leave->cancel($this->fileLeave(['date_from' => '2026-11-06', 'date_to' => '2026-11-06']), 'Filed twice');

        // HR's reason is shown as typed, never as markup.
        $this->assertSame([
            'Leave approved' => 'Your Vacation Leave, Nov 3–4, 2026, was approved.',
            'Leave cancelled' => 'HR cancelled your Vacation Leave, Nov 6, 2026: Filed twice. What it took from your balance was given back.',
            'Leave disapproved' => 'Your Vacation Leave, Nov 5, 2026, was disapproved: Short of staff &lt;b&gt;that&lt;/b&gt; week. What it took from your balance was given back.',
        ], $account->notifications->mapWithKeys(fn ($notice): array => [$notice->data['title'] => $notice->data['body']])->sortKeys()->all());
    }

    public function test_an_employee_who_cancels_their_own_leave_is_not_told(): void
    {
        $account = User::factory()->employee($this->employee)->create();
        $application = $this->fileLeave();
        $this->actingAs($account);

        app(LeaveApplications::class)->cancel($application, 'Plans changed');

        $this->assertSame(0, $account->notifications()->count());
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
