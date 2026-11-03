<?php

namespace Tests\Feature\DtrUploads;

use App\Enums\EmploymentStatus;
use App\Filament\Resources\DtrUploads\Pages\ListDtrUploads;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
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
            ->assertMountedActionModalSee(['23 min, 2 times', '25 min, 2 times', '48 min = 0.100 day', '21 days', 'Present', 'Absent']);
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
}
