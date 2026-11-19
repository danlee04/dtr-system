<?php

namespace Tests\Feature\DtrUploads;

use App\Enums\DtrSlot;
use App\Enums\EmploymentStatus;
use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use App\Enums\LedgerKind;
use App\Enums\ManualTapReason;
use App\Filament\Resources\DtrUploads\DtrUploadResource;
use App\Filament\Resources\DtrUploads\Pages\ListDtrUploads;
use App\Models\DayException;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\ManualTap;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\DtrDeduction;
use Carbon\CarbonPeriod;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\DtrPdf;
use Tests\TestCase;

class DtrUploadResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // October's DTRs are out, so the upload's month starts on October.
        $this->travelTo('2026-11-03 09:00');
    }

    private function pdf(string $fileName, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($fileName, $contents);
    }

    private function dave(): Employee
    {
        return Employee::factory()->create(['last_name' => 'LUCAR', 'first_name' => 'DAVE', 'middle_name' => 'AVILA']);
    }

    /**
     * The body of the last notification sent, as the browser gets it. Read it
     * before assertNotified(), which takes the notifications off the session.
     */
    private function notificationBody(): string
    {
        return (string) data_get(last(session('filament.claimed_notifications') ?? session('filament.notifications', [])), 'body');
    }

    public function test_hr_uploads_dtrs_and_each_goes_to_the_employee_it_names(): void
    {
        $hr = User::factory()->create();
        $this->actingAs($hr);
        $dave = $this->dave();

        $page = Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: [
                'files' => [
                    $this->pdf('LUCAR, DAVE A..pdf', (new DtrPdf('LUCAR, DAVE A.'))->day(1, ['am_arrival' => '07:51 am'])->contents()),
                    $this->pdf('SANTOS, ANA.pdf', (new DtrPdf('SANTOS, ANA'))->contents()),
                ],
            ])
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('1 needs an employee', $this->notificationBody());
        $page->assertNotified('Read 2 DTRs');
        $this->assertSame(
            [['LUCAR, DAVE A..pdf', $dave->id, $hr->id], ['SANTOS, ANA.pdf', null, $hr->id]],
            DtrUpload::query()->orderBy('file_name')->get()->map(fn (DtrUpload $upload): array => [$upload->file_name, $upload->employee_id, $upload->uploaded_by_user_id])->all(),
        );
        $this->assertSame(1, DtrDay::query()->count());
    }

    public function test_the_uploaded_files_are_not_kept(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: ['files' => [$this->pdf('dtr.pdf', (new DtrPdf)->contents())]]);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_file_path_forged_in_the_browser_is_refused(): void
    {
        // The upload's state lives in the browser. A path typed into it must
        // not let anyone read, or delete, some other file on the private disk.
        $this->actingAs(User::factory()->create());
        Storage::disk('local')->put('someone-elses/dtr.pdf', (new DtrPdf)->contents());

        Livewire::test(ListDtrUploads::class)
            ->mountAction('uploadDtrs')
            ->set('mountedActions.0.data.files', ['forged' => 'someone-elses/dtr.pdf'])
            ->callMountedAction()
            ->assertHasFormErrors(['files']);

        $this->assertTrue(Storage::disk('local')->exists('someone-elses/dtr.pdf'));
        $this->assertSame(0, DtrUpload::query()->count());
    }

    public function test_a_file_that_is_not_a_dtr_is_named_safely_and_the_rest_are_still_read(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: [
                'files' => [
                    $this->pdf('<script>payslip.pdf', DtrPdf::document('Payslip for October')),
                    $this->pdf('dtr.pdf', (new DtrPdf)->contents()),
                ],
            ]);

        $body = $this->notificationBody();
        $page->assertNotified('Read 1 DTR');
        $this->assertStringContainsString('&lt;script&gt;payslip.pdf: It has no &quot;(Employee Name)&quot; line', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertSame(['dtr.pdf'], DtrUpload::query()->pluck('file_name')->all());
    }

    public function test_a_dtr_printed_for_another_month_than_the_one_chosen_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: [
                'month' => '2026-10',
                'files' => [
                    $this->pdf('LUCAR, DAVE A..pdf', (new DtrPdf('LUCAR, DAVE A.'))->contents()),
                    $this->pdf('SANTOS, ANA.pdf', (new DtrPdf('SANTOS, ANA', 'SEPTEMBER 1 - 30, 2026'))->contents()),
                ],
            ]);

        $body = $this->notificationBody();
        $page->assertNotified('Read 1 DTR');
        $this->assertStringContainsString('SANTOS, ANA.pdf: It prints September 2026, not October 2026.', $body);
        $this->assertSame(['LUCAR, DAVE A..pdf'], DtrUpload::query()->pluck('file_name')->all());
    }

    public function test_a_dtr_already_uploaded_is_skipped_and_named(): void
    {
        $this->actingAs(User::factory()->create());
        DtrUpload::factory()->for($this->dave())->create();

        $page = Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: [
                'month' => '2026-10',
                'files' => [
                    $this->pdf('LUCAR, DAVE A..pdf', (new DtrPdf('LUCAR, DAVE A.'))->contents()),
                    $this->pdf('SANTOS, ANA.pdf', (new DtrPdf('SANTOS, ANA'))->contents()),
                ],
            ]);

        $body = $this->notificationBody();
        $page->assertNotified('Read 1 DTR');
        $this->assertStringContainsString('LUCAR, DAVE A..pdf: LUCAR, DAVE A. already has a DTR for October 2026. Delete it first to upload this one.', $body);
        $this->assertSame(2, DtrUpload::query()->count());
    }

    public function test_the_upload_takes_a_file_of_up_to_25_mb(): void
    {
        $this->actingAs(User::factory()->create());

        // 20 MB: past Livewire's own 12 MB and the old 5 MB limit, within the 25 MB HR asked for.
        Livewire::test(ListDtrUploads::class)
            ->callAction('uploadDtrs', data: ['month' => '2026-10', 'files' => [UploadedFile::fake()->create('big.pdf', 20 * 1024, 'application/pdf')]])
            ->assertHasNoFormErrors();
    }

    public function test_the_list_runs_by_last_name_from_a_to_z(): void
    {
        $this->actingAs(User::factory()->create());
        $cruz = DtrUpload::factory()->for(Employee::factory()->state(['last_name' => 'CRUZ']))->create();
        // No employee yet: its printed name, surname first, takes the employee's place.
        $abao = DtrUpload::factory()->unmatched()->create(['employee_name' => 'ABAO, LLOYD B.']);
        $santos = DtrUpload::factory()->for(Employee::factory()->state(['last_name' => 'SANTOS']))->create();

        Livewire::test(ListDtrUploads::class)
            ->assertCanSeeTableRecords([$abao, $cruz, $santos], inOrder: true);
    }

    public function test_the_list_opens_on_the_latest_month_and_shows_another_when_chosen(): void
    {
        $this->actingAs(User::factory()->create());
        $october = DtrUpload::factory()->create();
        $september = DtrUpload::factory()->create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);

        // The month picker sits in the toolbar and applies as it changes, with no Apply button.
        $this->get(DtrUploadResource::getUrl('index'))
            ->assertSee(['wire:model.live="tableFilters.month.value"', 'wire:model.live="tableFilters.needs_employee.isActive"', 'September 2026'], false);

        Livewire::test(ListDtrUploads::class)
            ->assertCanSeeTableRecords([$october])
            ->assertCanNotSeeTableRecords([$september])
            ->set('tableFilters.month.value', '2026-09')
            ->assertCanSeeTableRecords([$september])
            ->assertCanNotSeeTableRecords([$october]);
    }

    public function test_the_bells_link_lists_the_dtrs_needing_an_employee_from_every_month(): void
    {
        $this->actingAs(User::factory()->create());
        DtrUpload::factory()->create();
        $september = DtrUpload::factory()->unmatched()->create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);

        Livewire::withQueryParams(['filters' => ['needs_employee' => ['isActive' => true]]])
            ->test(ListDtrUploads::class)
            ->assertCanSeeTableRecords([$september]);
    }

    public function test_hr_chooses_the_employee_for_a_dtr_whose_name_matched_nobody(): void
    {
        $this->actingAs(User::factory()->create());
        $dave = $this->dave();
        $upload = DtrUpload::factory()->unmatched()->create(['employee_name' => 'LUCAR, DAVID A.']);

        Livewire::test(ListDtrUploads::class)
            ->callAction(TestAction::make('chooseEmployee')->table($upload), data: ['employee_id' => $dave->id])
            ->assertHasNoFormErrors();

        $this->assertSame($dave->id, $upload->fresh()->employee_id);
    }

    public function test_choosing_an_employee_is_offered_only_while_a_dtr_has_none(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->assertActionHidden(TestAction::make('chooseEmployee')->table(DtrUpload::factory()->create()));
    }

    public function test_the_needs_an_employee_filter_lists_only_the_dtrs_without_one(): void
    {
        $this->actingAs(User::factory()->create());
        $matched = DtrUpload::factory()->create();
        $unmatched = DtrUpload::factory()->unmatched()->create();

        Livewire::test(ListDtrUploads::class)
            ->filterTable('needs_employee')
            ->assertCanSeeTableRecords([$unmatched])
            ->assertCanNotSeeTableRecords([$matched]);
    }

    /** October 1 on 8–12 and 1–5: 14 and 9 minutes late, 15 and 10 under. */
    private function computableUpload(EmploymentStatus $status = EmploymentStatus::Permanent): DtrUpload
    {
        $employee = Employee::factory()->create(['employment_status' => $status]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);

        return DtrUpload::factory()->for($employee)
            ->has(DtrDay::factory()->state(['date' => '2026-10-01', 'am_arrival' => '08:14', 'am_departure' => '11:45', 'pm_arrival' => '13:09', 'pm_departure' => '16:50']), 'days')
            ->create();
    }

    /** The same October 1, and every other work day of October on time: no absent day to charge. */
    private function checkedUpload(): DtrUpload
    {
        $upload = $this->computableUpload();

        foreach (CarbonPeriod::create('2026-10-02', '2026-10-31') as $date) {
            if (! $date->isWeekend()) {
                $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => '08:00', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
            }
        }

        return $upload;
    }

    public function test_the_view_shows_the_late_and_undertime_of_each_day_and_the_month(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($this->computableUpload()))
            // October 2026 has 22 weekdays; only the 1st has taps. Each of the 21 absent days is 8 hours undertime too, and that part comes off SL first.
            ->assertMountedActionModalSee(['0 hrs 23 min', '2 times', '168 hrs 25 min', '23 times', '21.100 days', 'Late + undertime · 168 hrs 48 min', 'equivalent to leave credits', '21 days', '21.000 days from SL, then VL', "480 (8\u{00A0}hrs)", 'Present', 'Absent'])
            // The Undertime column already says it; a note repeating it was clutter (HR, 2026-11-19).
            ->assertMountedActionModalDontSee('hrs absent');
    }

    public function test_a_days_minutes_from_an_hour_up_show_the_hours_beside_them(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());
        $upload = $this->computableUpload();
        // In at 9:13 and no tap after: 73 minutes late, and the afternoon with no tap is 240 minutes undertime.
        $upload->days()->create(['date' => '2026-10-16', 'am_arrival' => '09:13']);

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            // Each number keeps its unit (a no-break space), so a narrow column breaks only between them.
            ->assertMountedActionModalSee(["73 (1\u{00A0}hr 13\u{00A0}min)", "240 (4\u{00A0}hrs)"]);
    }

    public function test_the_list_shows_each_dtrs_late_and_undertime(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());
        $this->computableUpload();

        Livewire::test(ListDtrUploads::class)
            ->assertSeeText(['10128 min', '21.100 day']);
    }

    public function test_a_job_order_dtr_is_shown_but_not_computed(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());
        $upload = $this->computableUpload(EmploymentStatus::JobOrder);

        Livewire::test(ListDtrUploads::class)
            ->assertDontSeeText('48 min')
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSee(['earn no leave credits', '8:14 AM']);
    }

    public function test_the_view_shows_every_date_of_the_month_with_the_times_printed(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = DtrUpload::factory()
            ->has(DtrDay::factory()->state(['date' => '2026-10-01', 'pm_departure' => '17:13', 'remarks' => 'Seminar']), 'days')
            ->create();

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            // Oct 31 printed nothing, and still has its row, as on paper.
            ->assertMountedActionModalSee(["Thu, Oct\u{00A0}1", '5:13 PM', 'Seminar', "Sat, Oct\u{00A0}31"]);
    }

    public function test_a_bare_number_printed_in_a_days_remarks_is_not_shown(): void
    {
        $this->actingAs(User::factory()->create());
        // IT has not said what "-479" means, and it confused HR (2026-11-18).
        $upload = DtrUpload::factory()
            ->has(DtrDay::factory()->state(['date' => '2026-10-14', 'am_arrival' => '12:15', 'remarks' => '-479']), 'days')
            ->create();

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSee('12:15 PM')
            ->assertMountedActionModalDontSee('-479');
    }

    /** October 2 on 8–12 and 1–5, printed without its time out. */
    private function uploadWithoutTimeOut(EmploymentStatus $status = EmploymentStatus::Permanent): DtrUpload
    {
        $this->travelTo('2026-11-03 09:00');
        $employee = Employee::factory()->create(['employment_status' => $status]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);

        return DtrUpload::factory()->for($employee)
            ->has(DtrDay::factory()->state(['date' => '2026-10-02', 'am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_arrival' => '12:58', 'pm_departure' => null]), 'days')
            ->create();
    }

    /** @return list<TestAction> the DTR's view, then the fill-in modal for a date */
    private function fillIn(DtrUpload $upload, string $date, DtrSlot $slot = DtrSlot::PmDeparture): array
    {
        return [
            TestAction::make('view')->table($upload),
            TestAction::make('fillDay')->schemaComponent('dtr')->arguments(['date' => $date, 'slot' => $slot->value]),
        ];
    }

    public function test_hr_copies_in_a_time_written_on_the_signed_dtr(): void
    {
        $hr = User::factory()->create();
        $this->actingAs($hr);
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: [
                'pm_departure' => '16:30',
                'reason' => ManualTapReason::ForgotToTap->value,
                'remarks' => 'Signed by the section chief',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('1 time saved')
            // Back in the DTR: the time out it now has is 30 minutes early, on top of the 21 absent days' 8 hours each.
            ->assertMountedActionModalSee(['168 hrs 30 min', '22 times', 'HR: forgot to tap']);

        $tap = ManualTap::sole();
        $this->assertSame(
            [$upload->employee_id, '2026-10-02', DtrSlot::PmDeparture, '16:30:00', ManualTapReason::ForgotToTap, 'Signed by the section chief', $hr->id],
            [$tap->employee_id, $tap->date->toDateString(), $tap->slot, $tap->time, $tap->reason, $tap->remarks, $tap->filled_by_user_id],
        );
    }

    public function test_a_time_hr_copies_in_may_be_from_a_gate_pass(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: ['pm_departure' => '17:00', 'reason' => ManualTapReason::GatePass->value])
            ->assertHasNoFormErrors()
            ->assertMountedActionModalSee('HR: gate pass');

        $this->assertSame(ManualTapReason::GatePass, ManualTap::sole()->reason);
    }

    public function test_the_view_marks_the_blanks_to_fill_and_the_times_hr_filled(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        ManualTap::factory()->for($upload->employee)->create(['date' => '2026-10-05', 'slot' => DtrSlot::AmArrival, 'time' => '08:03']);

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSee(['Blanks to fill', 'Add P.M. departure, Fri, Oct 2', '8:03 AM', 'HR']);
    }

    public function test_saving_takes_at_least_one_time(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: ['pm_departure' => null, 'reason' => ManualTapReason::ForgotToTap->value])
            ->assertNotified('Nothing to save: enter or change a time');

        $this->assertDatabaseCount(ManualTap::class, 0);
    }

    public function test_a_time_out_of_order_is_caught_as_an_am_pm_mix_up(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: ['pm_departure' => '05:00', 'reason' => ManualTapReason::ForgotToTap->value])
            ->assertHasFormErrors(['pm_departure']);

        $this->assertDatabaseCount(ManualTap::class, 0);
    }

    /** The device printed 12:15 PM for the time in; the signed paper says 8:00 AM. */
    private function uploadWithAWrongTimeIn(): DtrUpload
    {
        $upload = $this->uploadWithoutTimeOut();
        $upload->days()->create(['date' => '2026-10-14', 'am_arrival' => '12:15', 'am_departure' => '12:29', 'pm_arrival' => '12:49', 'pm_departure' => '17:01']);

        return $upload;
    }

    public function test_hr_corrects_a_time_the_device_printed_wrong(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithAWrongTimeIn();

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->fillIn($upload, '2026-10-14', DtrSlot::AmArrival))
            ->assertActionDataSet(['am_arrival' => '12:15', 'pm_departure' => '17:01', 'reason' => ManualTapReason::DeviceError])
            ->fillForm(['am_arrival' => '08:00'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('1 time saved')
            // Back in the DTR: the 255 minutes late are gone, and the time shows as HR's.
            ->assertMountedActionModalSee(['Change A.M. arrival, Wed, Oct 14', '8:00 AM', 'HR: biometric error'])
            ->assertMountedActionModalDontSee('255');

        // Only the time HR changed is kept; the three left as printed need none.
        $tap = ManualTap::sole();
        $this->assertSame([DtrSlot::AmArrival, '08:00:00', ManualTapReason::DeviceError], [$tap->slot, $tap->time, $tap->reason]);
    }

    public function test_a_printed_time_can_be_corrected_but_not_left_blank(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithAWrongTimeIn();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-14', DtrSlot::AmArrival), data: ['am_arrival' => null])
            ->assertHasFormErrors(['am_arrival' => 'required']);

        $this->assertDatabaseCount(ManualTap::class, 0);
    }

    public function test_typing_the_printed_time_back_removes_hrs(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithAWrongTimeIn();
        $tap = ManualTap::factory()->for($upload->employee)->create(['date' => '2026-10-14', 'slot' => DtrSlot::AmArrival, 'time' => '08:00']);

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-14', DtrSlot::AmArrival), data: ['am_arrival' => '12:15'])
            ->assertHasNoFormErrors()
            ->assertNotified('HR times removed');

        $this->assertModelMissing($tap);
    }

    public function test_dates_outside_the_dtr_or_with_nothing_to_fill_cannot_be_opened(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        // A hidden action cannot be mounted or called, even by a crafted request. November is not on this DTR, and October 3 is a Saturday.
        foreach (['2026-11-02', '2026-10-03', 'not a date'] as $date) {
            Livewire::test(ListDtrUploads::class)->assertActionHidden($this->fillIn($upload, $date));
        }
    }

    public function test_a_fill_in_left_open_while_the_month_is_reprinted_saves_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        $page = Livewire::test(ListDtrUploads::class)->mountAction($this->fillIn($upload, '2026-10-02'));

        // HR deletes the upload the modal was opened on, to upload IT's reprint.
        $upload->delete();

        $page->fillForm(['pm_departure' => '17:00', 'reason' => ManualTapReason::ForgotToTap->value])->callMountedAction();

        $this->assertDatabaseCount(ManualTap::class, 0);
    }

    public function test_a_job_order_dtr_has_no_blanks_to_fill(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut(EmploymentStatus::JobOrder);

        Livewire::test(ListDtrUploads::class)->assertActionHidden($this->fillIn($upload, '2026-10-02'));
    }

    public function test_the_fill_in_modal_opens_with_the_times_reason_and_note_hr_left(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        ManualTap::factory()->for($upload->employee)->create(['date' => '2026-10-02', 'slot' => DtrSlot::PmDeparture, 'time' => '17:00', 'reason' => ManualTapReason::OfficialBusiness, 'remarks' => 'Meeting at PHO']);

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->fillIn($upload, '2026-10-02'))
            ->assertActionDataSet(['pm_departure' => '17:00', 'reason' => ManualTapReason::OfficialBusiness, 'remarks' => 'Meeting at PHO']);
    }

    public function test_hr_changes_a_time_it_filled_and_clearing_it_removes_it(): void
    {
        $hr = User::factory()->create();
        $this->actingAs($hr);
        $upload = $this->uploadWithoutTimeOut();
        $tap = ManualTap::factory()->for($upload->employee)->create(['date' => '2026-10-02', 'slot' => DtrSlot::PmDeparture, 'time' => '17:00']);

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: ['pm_departure' => '17:10', 'reason' => ManualTapReason::OfficialBusiness->value])
            ->assertHasNoFormErrors();

        $this->assertSame(['17:10:00', ManualTapReason::OfficialBusiness, $hr->id], [$tap->fresh()->time, $tap->fresh()->reason, $tap->fresh()->filled_by_user_id]);

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02'), data: ['pm_departure' => null, 'reason' => ManualTapReason::OfficialBusiness->value])
            ->assertNotified('HR times removed');

        $this->assertModelMissing($tap);
    }

    /** @return list<TestAction> the DTR's view, then the OB or travel order form for a date */
    private function addException(DtrUpload $upload, string $date): array
    {
        return [
            TestAction::make('view')->table($upload),
            TestAction::make('addDayException')->schemaComponent('dtr')->arguments(['date' => $date]),
        ];
    }

    public function test_hr_records_an_ob_for_an_absent_day_from_the_dtr(): void
    {
        $hr = User::factory()->create();
        $this->actingAs($hr);
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->addException($upload, '2026-10-29'), data: [
                'type' => ExceptionType::OfficialBusiness->value,
                'date_from' => '2026-10-29',
                'date_to' => '2026-10-29',
                'coverage' => LeaveCoverage::WholeDay->value,
                'reference_no' => 'OB-2026-130',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Official business recorded: Oct 29, 2026')
            ->assertMountedActionModalSee('Official business');

        // The same record the OB & travel orders screen lists.
        $exception = DayException::sole();
        $this->assertSame(
            [$upload->employee_id, ExceptionType::OfficialBusiness, '2026-10-29', LeaveCoverage::WholeDay, 'OB-2026-130', $hr->id],
            [$exception->employee_id, $exception->type, $exception->date_from->toDateString(), $exception->coverage, $exception->reference_no, $exception->created_by_user_id],
        );
    }

    public function test_a_day_on_an_ob_shows_the_schedules_times_marked_ob(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        DayException::factory()->for($upload->employee)->create(['date_from' => '2026-10-29', 'date_to' => '2026-10-29']);

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSeeHtml('<span class="dtr-grid-time">8:00 AM</span> <span class="dtr-grid-hr-tag">OB</span>');
    }

    public function test_hr_marks_leave_for_an_absent_day_from_the_dtr_without_an_application(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->addException($upload, '2026-10-29'), data: [
                'type' => ExceptionType::Leave->value,
                'date_from' => '2026-10-29',
                'date_to' => '2026-10-29',
                'coverage' => LeaveCoverage::WholeDay->value,
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Leave recorded: Oct 29, 2026');

        $this->assertSame(ExceptionType::Leave, DayException::sole()->type);
        // A mark on the DTR only: no leave application, and no credits taken.
        $this->assertDatabaseCount(LeaveApplication::class, 0);
        $this->assertDatabaseCount(LeaveLedgerEntry::class, 0);
    }

    public function test_the_ob_form_starts_on_the_date_and_the_half_with_no_tap(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        $upload->days()->create(['date' => '2026-10-05', 'am_arrival' => '07:50', 'am_departure' => '12:01']);

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->addException($upload, '2026-10-05'))
            ->assertActionDataSet(['date_from' => '2026-10-05', 'date_to' => '2026-10-05', 'coverage' => LeaveCoverage::SecondHalf]);
    }

    public function test_an_ob_overlapping_one_already_recorded_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        DayException::factory()->for($upload->employee)->create(['date_from' => '2026-10-30', 'date_to' => '2026-10-30']);

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->addException($upload, '2026-10-28'), data: [
                'type' => ExceptionType::TravelOrder->value,
                'date_from' => '2026-10-28',
                'date_to' => '2026-10-30',
                'coverage' => LeaveCoverage::WholeDay->value,
            ])
            ->assertHasFormErrors(['date_from']);

        $this->assertSame(1, DayException::query()->count());
    }

    public function test_days_with_taps_in_each_half_or_off_work_offer_no_ob(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        // October 2 has a tap in each half; October 3 is a Saturday.
        foreach (['2026-10-02', '2026-10-03'] as $date) {
            Livewire::test(ListDtrUploads::class)->assertActionHidden($this->addException($upload, $date));
        }
    }

    public function test_remove_hr_times_clears_the_dates_times_and_keeps_the_dtr_open(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        $tap = ManualTap::factory()->for($upload->employee)->create(['date' => '2026-10-02', 'slot' => DtrSlot::PmDeparture]);

        Livewire::test(ListDtrUploads::class)
            ->callAction([...$this->fillIn($upload, '2026-10-02'), TestAction::make('removeHrTimes')])
            ->assertNotified('HR times removed')
            ->assertMountedActionModalSee('Blanks to fill');

        $this->assertModelMissing($tap);
    }

    private function final(DtrUpload $upload): DtrUpload
    {
        app(DtrDeduction::class)->finalize($upload, User::factory()->create());

        return $upload;
    }

    /** @return list<TestAction> the DTR's view, then a button in its footer */
    private function footer(DtrUpload $upload, string $action): array
    {
        return [TestAction::make('view')->table($upload), TestAction::make($action)];
    }

    public function test_hr_marks_a_checked_dtr_final_and_vl_is_deducted(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $upload = $this->checkedUpload();

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->footer($upload, 'finalize'))
            ->assertMountedActionModalSee('Deduct 0.100 days from VL for late and undertime.')
            ->callMountedAction()
            ->assertNotified('DTR marked final');

        $this->assertSame([['-0.100', LedgerKind::DtrDeduction]], LeaveLedgerEntry::all()->map(fn (LeaveLedgerEntry $row): array => [$row->days, $row->kind])->all());
        $this->assertTrue($upload->refresh()->isFinal());
    }

    public function test_the_final_confirmation_names_the_absent_days_it_takes_from_sl_then_vl(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->footer($this->computableUpload(), 'finalize'))
            ->assertMountedActionModalSee('Deduct 0.100 days from VL for late and undertime, and 21.000 days for 21 absent days from SL, then VL.');
    }

    public function test_the_final_confirmation_counts_the_blanks_still_empty(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->mountAction($this->footer($this->uploadWithoutTimeOut(), 'finalize'))
            ->assertMountedActionModalSee('1 blank still empty.');
    }

    public function test_a_final_dtr_is_locked(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();
        $locked = [$this->fillIn($upload, '2026-10-02'), $this->addException($upload, '2026-10-29'), TestAction::make('delete')->table($upload), $this->footer($upload, 'finalize')];

        $open = Livewire::test(ListDtrUploads::class)->assertActionHidden($this->footer($upload, 'reopen'));
        foreach ($locked as $action) {
            $open->assertActionVisible($action);
        }

        $this->final($upload);

        $final = Livewire::test(ListDtrUploads::class)->assertActionVisible($this->footer($upload, 'reopen'));
        foreach ($locked as $action) {
            $final->assertActionHidden($action);
        }
    }

    public function test_hr_reopens_a_final_dtr_with_a_reason(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $upload = $this->final($this->checkedUpload());

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->footer($upload, 'reopen'), data: ['reopen_reason' => ''])
            ->assertHasActionErrors(['reopen_reason' => 'required']);
        $this->assertTrue($upload->refresh()->isFinal());

        Livewire::test(ListDtrUploads::class)
            ->callAction($this->footer($upload, 'reopen'), data: ['reopen_reason' => 'Leave filed late'])
            ->assertHasNoActionErrors()
            ->assertNotified('DTR reopened');

        $this->assertFalse($upload->refresh()->isFinal());
        $this->assertSame(['-0.100', '0.100'], LeaveLedgerEntry::query()->orderBy('id')->pluck('days')->all());
    }

    public function test_final_is_not_offered_on_a_job_order_dtr(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->assertActionHidden($this->footer($this->computableUpload(EmploymentStatus::JobOrder), 'finalize'));
    }

    public function test_choosing_an_employee_does_not_replace_a_final_dtr(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $dave = $this->dave();
        ScheduleAssignment::factory()->for($dave)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);
        $final = $this->final(DtrUpload::factory()->for($dave)->create());
        $unassigned = DtrUpload::factory()->create(['employee_id' => null, 'employee_name' => 'LUCAR, D.']);

        Livewire::test(ListDtrUploads::class)
            ->callAction(TestAction::make('chooseEmployee')->table($unassigned), data: ['employee_id' => $dave->id])
            ->assertHasActionErrors(['employee_id']);

        $this->assertModelExists($final);
        $this->assertNull($unassigned->refresh()->employee_id);
    }

    public function test_a_final_dtr_shows_when_and_by_whom_and_what_it_deducted(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $hr = User::factory()->create(['name' => 'Ana Reyes']);
        $this->actingAs($hr);
        $upload = $this->computableUpload();
        app(DtrDeduction::class)->finalize($upload, $hr);

        // The 21 absent days take October's 1.250 SL and 19.750 VL; VL also loses the 0.100 for late and undertime.
        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSee("Final · Nov 3, 2026 by Ana Reyes · SL \u{2212}1.250 · VL \u{2212}19.850")
            ->assertMountedActionModalDontSee('Changed since Final');
    }

    public function test_a_final_dtr_says_when_its_figures_changed_since(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $upload = $this->final($this->checkedUpload());
        // Read after Final: in at 9:13 and nothing after, 73 minutes late and 240 under; 361 minutes in all is 0.752 day.
        $upload->days()->whereDate('date', '2026-10-16')->update(['am_arrival' => '09:13', 'am_departure' => null, 'pm_arrival' => null, 'pm_departure' => null]);

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($upload))
            ->assertMountedActionModalSee('Changed since Final: deducted 0.100, now 0.752. Reopen and mark it final again.');
    }

    public function test_the_list_marks_final_dtrs_and_filters_the_rest(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $this->actingAs(User::factory()->create());
        $final = $this->final($this->computableUpload());
        $open = $this->computableUpload();

        Livewire::test(ListDtrUploads::class)
            ->assertSeeText('21.100 day · Final')
            ->filterTable('not_final')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$final]);
    }
}
