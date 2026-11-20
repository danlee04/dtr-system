<?php

namespace Tests\Feature\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\DtrDeduction;
use App\Services\Leave\LeaveLedger;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DtrDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-03 09:00');
        config(['leave.start_date' => '2026-10-01']);
        $this->hr = User::factory()->create();
    }

    /**
     * October 2026: on the 1st 14 + 9 minutes late and 15 + 10 under, 48 minutes
     * in all, which the CSC table makes 0.100 day; every other work day on time,
     * except the dates given as absent.
     *
     * @param  array<string, string>  $times
     * @param  list<string>  $absentOn
     */
    private function upload(EmploymentStatus $status = EmploymentStatus::Permanent, array $times = ['am_arrival' => '08:14', 'am_departure' => '11:45', 'pm_arrival' => '13:09', 'pm_departure' => '16:50'], array $absentOn = []): DtrUpload
    {
        $employee = Employee::factory()->create(['employment_status' => $status]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);
        $upload = DtrUpload::factory()->for($employee)->create();
        $upload->days()->create(['date' => '2026-10-01', ...$times]);

        foreach (CarbonPeriod::create('2026-10-02', '2026-10-31') as $date) {
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $absentOn, true)) {
                $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => '08:00', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
            }
        }

        return $upload;
    }

    /** @return list<array{string, string, LedgerKind}> each row's type, days and kind, in the order posted */
    private function rows(): array
    {
        return LeaveLedgerEntry::query()->whereIn('kind', [LedgerKind::DtrDeduction, LedgerKind::DtrReopened])->orderBy('id')->get()
            ->map(fn (LeaveLedgerEntry $row): array => [$row->leave_type->value, $row->days, $row->kind])->all();
    }

    private function deduction(): DtrDeduction
    {
        return app(DtrDeduction::class);
    }

    public function test_final_deducts_the_months_late_and_undertime_from_vl(): void
    {
        $upload = $this->upload();

        $this->deduction()->finalize($upload, $this->hr);

        $row = LeaveLedgerEntry::sole();
        $this->assertSame(
            [$upload->employee_id, LeaveType::Vacation, '-0.100', LedgerKind::DtrDeduction, '2026-10', '2026-11-03', $this->hr->id, $upload->id, 'October 2026 DTR: 48 min late and undertime'],
            [$row->employee_id, $row->leave_type, $row->days, $row->kind, $row->period, $row->posted_on->toDateString(), $row->created_by_user_id, $row->dtr_upload_id, $row->remarks],
        );
        $upload->refresh();
        $this->assertTrue($upload->isFinal());
        $this->assertSame(['2026-11-03 09:00:00', $this->hr->id], [$upload->finalized_at->toDateTimeString(), $upload->finalized_by_user_id]);
    }

    public function test_vl_goes_negative_when_it_is_short(): void
    {
        $upload = $this->upload();
        LeaveLedgerEntry::factory()->for($upload->employee)->create(['leave_type' => LeaveType::Vacation, 'days' => '0.050', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);

        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame('-0.050', app(LeaveLedger::class)->balances($upload->employee)['VL']);
    }

    public function test_a_dtr_before_the_leave_start_is_final_without_a_deduction(): void
    {
        config(['leave.start_date' => '2026-12-01']);
        $upload = $this->upload();

        $this->assertSame('Nothing is deducted: this DTR is from before the leave credits start (Dec 1, 2026).', $this->deduction()->whyNothing($upload));
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame(0, LeaveLedgerEntry::count());
        $this->assertTrue($upload->refresh()->isFinal());
    }

    public function test_no_start_date_means_no_deduction(): void
    {
        config(['leave.start_date' => null]);
        $upload = $this->upload();

        $this->assertSame('Nothing is deducted: the leave start date is not set.', $this->deduction()->whyNothing($upload));
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_nothing_late_or_under_posts_no_row(): void
    {
        $upload = $this->upload(times: ['am_arrival' => '08:00', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);

        $this->assertSame(['0.000', 'Nothing is deducted: no late, undertime or absence.'], [$this->deduction()->due($upload), $this->deduction()->whyNothing($upload)]);
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_a_second_final_is_refused_and_posts_nothing(): void
    {
        $upload = $this->upload();
        $this->deduction()->finalize($upload, $this->hr);

        try {
            // The same model as the first click had: the check must read the row, not the stale copy.
            $this->deduction()->finalize($upload->fresh()->forceFill(['finalized_at' => null]), $this->hr);
            $this->fail('A second Final went through.');
        } catch (ValidationException $exception) {
            $this->assertSame(['dtr' => ['This DTR is already final.']], $exception->errors());
        }

        $this->assertSame(1, LeaveLedgerEntry::count());
    }

    public function test_a_job_order_dtr_cannot_be_final(): void
    {
        $upload = $this->upload(EmploymentStatus::JobOrder);

        $this->expectException(ValidationException::class);

        try {
            $this->deduction()->finalize($upload, $this->hr);
        } finally {
            $this->assertFalse($upload->refresh()->isFinal());
        }
    }

    public function test_reopen_gives_the_vl_back_and_final_again_deducts_anew(): void
    {
        $upload = $this->upload();
        $this->deduction()->finalize($upload, $this->hr);

        $this->deduction()->reopen($upload, $this->hr, '  Leave filed late ');
        $this->assertSame([null, null], [$upload->refresh()->finalized_at, $upload->finalized_by_user_id]);

        $this->deduction()->finalize($upload, $this->hr);

        $rows = LeaveLedgerEntry::query()->orderBy('id')->get();
        $this->assertSame(['-0.100', '0.100', '-0.100'], $rows->pluck('days')->all());
        $this->assertSame([LedgerKind::DtrDeduction, LedgerKind::DtrReopened, LedgerKind::DtrDeduction], $rows->pluck('kind')->all());
        $this->assertSame('October 2026 DTR reopened: Leave filed late', $rows[1]->remarks);
        $this->assertSame('0.100', $this->deduction()->deducted($upload));
    }

    public function test_absent_days_come_off_sl_then_vl(): void
    {
        // Two days absent with no leave filed, 2.000 leave days. Posting what is due first adds October's 1.250 to the 0.250 SL.
        $upload = $this->upload(absentOn: ['2026-10-02', '2026-10-05']);
        LeaveLedgerEntry::factory()->for($upload->employee)->create(['leave_type' => LeaveType::Sick, 'days' => '0.250', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);

        $this->assertSame('2.100', $this->deduction()->due($upload));
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame([
            ['VL', '-0.100', LedgerKind::DtrDeduction],
            ['SL', '-1.500', LedgerKind::DtrDeduction],
            ['VL', '-0.500', LedgerKind::DtrDeduction],
        ], $this->rows());
        $this->assertSame('October 2026 DTR: 2 absent days', LeaveLedgerEntry::query()->where('leave_type', 'SL')->where('kind', LedgerKind::DtrDeduction)->sole()->remarks);
        $this->assertSame('0.000', app(LeaveLedger::class)->balance($upload->employee, LeaveType::Sick));
    }

    public function test_absent_days_take_vl_below_zero_once_sl_is_used_up(): void
    {
        $upload = $this->upload(absentOn: ['2026-10-02', '2026-10-05', '2026-10-06']);

        $this->deduction()->finalize($upload, $this->hr);

        // October's 1.250 SL covers 1.250 of the 3.000; the rest takes VL past its own 1.250 credit.
        $this->assertSame([
            ['VL', '-0.100', LedgerKind::DtrDeduction],
            ['SL', '-1.250', LedgerKind::DtrDeduction],
            ['VL', '-1.750', LedgerKind::DtrDeduction],
        ], $this->rows());
        $this->assertSame(['-0.600', '0.000'], [
            app(LeaveLedger::class)->balance($upload->employee, LeaveType::Vacation),
            app(LeaveLedger::class)->balance($upload->employee, LeaveType::Sick),
        ]);
    }

    public function test_reopen_gives_back_each_leave_type_it_took(): void
    {
        $upload = $this->upload(absentOn: ['2026-10-02', '2026-10-05']);
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame(['SL' => '1.250', 'VL' => '0.850'], $this->deduction()->deductedByType($upload));
        $this->deduction()->reopen($upload, $this->hr, 'Sick leave filed late');

        $this->assertSame([
            ['VL', '-0.100', LedgerKind::DtrDeduction],
            ['SL', '-1.250', LedgerKind::DtrDeduction],
            ['VL', '-0.750', LedgerKind::DtrDeduction],
            ['SL', '1.250', LedgerKind::DtrReopened],
            ['VL', '0.850', LedgerKind::DtrReopened],
        ], $this->rows());
        $this->assertSame([[], '0.000'], [$this->deduction()->deductedByType($upload), $this->deduction()->deducted($upload)]);
    }

    public function test_reopen_needs_a_reason_and_a_final_dtr(): void
    {
        $upload = $this->upload();

        try {
            $this->deduction()->reopen($upload, $this->hr, 'Leave filed late');
            $this->fail('An open DTR was reopened.');
        } catch (ValidationException $exception) {
            $this->assertSame(['dtr' => ['This DTR is not final.']], $exception->errors());
        }

        $this->deduction()->finalize($upload, $this->hr);

        try {
            $this->deduction()->reopen($upload, $this->hr, '  ');
            $this->fail('A DTR was reopened without a reason.');
        } catch (ValidationException $exception) {
            $this->assertSame(['reopen_reason' => ['Give the reason for reopening.']], $exception->errors());
        }

        $this->assertTrue($upload->refresh()->isFinal());
    }

    public function test_deleting_a_reopened_dtr_keeps_its_ledger_rows(): void
    {
        $upload = $this->upload();
        $this->deduction()->finalize($upload, $this->hr);
        $this->deduction()->reopen($upload, $this->hr, 'Wrong employee');

        $upload->delete();

        $this->assertSame([null, null], LeaveLedgerEntry::query()->pluck('dtr_upload_id')->all());
        $this->assertEquals(0, LeaveLedgerEntry::query()->sum('days'));
    }

    public function test_without_pay_is_how_far_the_final_took_vl_below_zero(): void
    {
        // The DTR takes 0.100: from 0.050, 0.050 of it is without pay; from −0.200, all of it; from 5.000, none.
        foreach (['0.050' => '0.050', '-0.200' => '0.100', '5.000' => '0.000'] as $opening => $withoutPay) {
            $upload = $this->upload();
            LeaveLedgerEntry::factory()->for($upload->employee)->create(['leave_type' => LeaveType::Vacation, 'days' => (string) $opening, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);

            $this->assertSame('0.000', $this->deduction()->withoutPay($upload), 'An open DTR has none.');
            $this->deduction()->finalize($upload, $this->hr);

            $this->assertSame($withoutPay, $this->deduction()->withoutPay($upload), "VL {$opening}");
        }
    }

    public function test_without_pay_follows_the_latest_final(): void
    {
        $upload = $this->upload();
        LeaveLedgerEntry::factory()->for($upload->employee)->create(['leave_type' => LeaveType::Vacation, 'days' => '0.050', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);
        $this->deduction()->finalize($upload, $this->hr);
        $this->assertSame('0.050', $this->deduction()->withoutPay($upload));

        // Reopened, VL topped up, then final again: judged on the VL of that moment.
        $this->deduction()->reopen($upload, $this->hr, 'Leave filed late');
        LeaveLedgerEntry::factory()->for($upload->employee)->create(['leave_type' => LeaveType::Vacation, 'days' => '1.000', 'kind' => LedgerKind::Adjustment, 'posted_on' => '2026-11-03']);
        $this->deduction()->finalize($upload, $this->hr);

        $this->assertSame('0.000', $this->deduction()->withoutPay($upload));
    }

    public function test_without_pay_does_not_count_a_later_months_deduction(): void
    {
        $october = $this->upload();
        LeaveLedgerEntry::factory()->for($october->employee)->create(['leave_type' => LeaveType::Vacation, 'days' => '0.050', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);
        // November 2, 20 minutes late: 0.042 day.
        $november = DtrUpload::factory()->for($october->employee)->create(['period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
        $november->days()->create(['date' => '2026-11-02', 'am_arrival' => '08:20', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);

        $this->deduction()->finalize($october, $this->hr);
        $this->deduction()->finalize($november, $this->hr);
        $this->deduction()->reopen($october, $this->hr, 'Leave filed late');
        $this->deduction()->finalize($october, $this->hr);

        // VL 0.050 against 0.100 and 0.042: 0.092 short in all. October's share stays 0.050; November's 0.042 is not counted again in it.
        $this->assertSame(['0.050', '0.042'], [$this->deduction()->withoutPay($october), $this->deduction()->withoutPay($november)]);
    }
}
