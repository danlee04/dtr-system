<?php

namespace Tests\Feature\DtrUploads;

use App\Enums\DtrSlot;
use App\Enums\EmploymentStatus;
use App\Enums\ManualTapReason;
use App\Filament\Resources\DtrUploads\Pages\ListDtrUploads;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\ManualTap;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
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

    public function test_the_view_shows_the_late_and_undertime_of_each_day_and_the_month(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());

        Livewire::test(ListDtrUploads::class)
            ->mountAction(TestAction::make('view')->table($this->computableUpload()))
            // October 2026 has 22 weekdays; only the 1st has taps.
            ->assertMountedActionModalSee(['23 min', '2 times', '25 min', '48 min', '= 0.100 day (CSC)', '21 days', 'Present', 'Absent']);
    }

    public function test_the_list_shows_each_dtrs_late_and_undertime(): void
    {
        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());
        $this->computableUpload();

        Livewire::test(ListDtrUploads::class)
            ->assertSeeText(['48 min', '0.100 day']);
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
            ->assertMountedActionModalSee(['Thu, Oct 1', '5:13 PM', 'Seminar', 'Sat, Oct 31']);
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
            // Back in the DTR: the time out it now has is 30 minutes early.
            ->assertMountedActionModalSee(['30 min', '1 time', 'HR: forgot to tap']);

        $tap = ManualTap::sole();
        $this->assertSame(
            [$upload->employee_id, '2026-10-02', DtrSlot::PmDeparture, '16:30:00', ManualTapReason::ForgotToTap, 'Signed by the section chief', $hr->id],
            [$tap->employee_id, $tap->date->toDateString(), $tap->slot, $tap->time, $tap->reason, $tap->remarks, $tap->filled_by_user_id],
        );
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
            ->assertNotified('Enter at least one time');

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

    public function test_a_printed_time_cannot_be_overwritten(): void
    {
        $this->actingAs(User::factory()->create());
        $upload = $this->uploadWithoutTimeOut();

        // P.M. arrival is printed, so the modal has no field for it; a value sent for it anyway is not saved.
        Livewire::test(ListDtrUploads::class)
            ->callAction($this->fillIn($upload, '2026-10-02', DtrSlot::PmArrival), data: [
                'pm_arrival' => '13:30',
                'pm_departure' => '17:00',
                'reason' => ManualTapReason::ForgotToTap->value,
            ]);

        $this->assertSame([DtrSlot::PmDeparture], ManualTap::query()->pluck('slot')->all());
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

        // The reprint replaces the upload the modal was opened on.
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
}
