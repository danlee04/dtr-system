<?php

namespace Tests\Feature\Leave;

use App\Enums\LedgerKind;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Leave\LeaveApplications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormFieldsTest extends TestCase
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

        foreach (['VL' => '5.000', 'SL' => '2.000'] as $type => $days) {
            LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => $type, 'days' => $days, 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
        }
    }

    public function test_filing_keeps_the_credits_it_was_charged_against(): void
    {
        // Three weekdays of SL against 2 days of it: the third comes out of VL.
        $application = $this->fileLeave(['leave_type' => 'SL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);

        $this->assertSame([
            'VL' => ['earned' => '5.000', 'less' => '1.000', 'balance' => '4.000'],
            'SL' => ['earned' => '2.000', 'less' => '2.000', 'balance' => '0.000'],
        ], $application->certification());
    }

    public function test_the_division_head_is_typed_on_the_application(): void
    {
        $this->assertSame('Dr. Juan Dela Cruz', $this->fileLeave(['division_head' => '  Dr. Juan Dela Cruz '])->division_head);
        $this->assertNull($this->fileLeave(['date_from' => '2026-11-09', 'date_to' => '2026-11-09', 'division_head' => ' '])->division_head);
    }

    public function test_an_application_without_credits_at_filing_certifies_nothing(): void
    {
        $this->assertNull(LeaveApplication::factory()->create(['credits_at_filing' => null])->certification());
    }

    public function test_hr_types_an_employees_position(): void
    {
        Livewire::test(EditEmployee::class, ['record' => $this->employee->getRouteKey()])
            ->fillForm(['position' => 'Nurse II'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nurse II', $this->employee->fresh()->position);
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
}
