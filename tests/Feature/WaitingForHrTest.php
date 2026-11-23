<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\LedgerKind;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\RosterEntry;
use App\Models\ScheduleAssignment;
use App\Services\WaitingForHr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingForHrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-23 09:00');
        config(['leave.start_date' => '2026-10-01']);
    }

    /** An employee with a schedule and an opening balance, so nothing about them waits. */
    private function settled(array $attributes = []): Employee
    {
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06', ...$attributes]);
        ScheduleAssignment::factory()->for($employee)->create(['effective_from' => '2026-01-01']);
        LeaveLedgerEntry::factory()->for($employee)->create(['kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);

        return $employee;
    }

    private function counts(): array
    {
        return app(WaitingForHr::class)->counts(CarbonImmutable::today());
    }

    public function test_it_counts_each_thing_waiting_for_hr(): void
    {
        $settled = $this->settled();
        LeaveApplication::factory()->for($settled)->create();
        DtrUpload::factory()->unmatched()->create();
        DtrUpload::factory()->for($settled)->create(['finalized_at' => null]);
        DtrUpload::factory()->for($settled)->create(['finalized_at' => now()]);

        // No schedule, and no opening balance though hired before the system took over.
        Employee::factory()->create(['date_hired' => '2019-06-03']);

        $this->assertSame([
            'pendingLeave' => 1,
            'unmatchedDtrs' => 1,
            'dtrsNotFinal' => 1,
            'withoutSchedule' => 1,
            'withoutOpenings' => 1,
        ], $this->counts());
    }

    public function test_a_nurse_on_this_months_roster_has_a_schedule(): void
    {
        $nurse = $this->settled();
        $nurse->scheduleAssignments()->delete();
        RosterEntry::factory()->for($nurse)->create(['date' => '2026-11-10']);

        $this->assertSame(0, $this->counts()['withoutSchedule']);
    }

    public function test_those_who_left_earn_nothing_or_joined_later_are_not_counted(): void
    {
        Employee::factory()->create(['is_active' => false]);
        Employee::factory()->create(['date_separated' => '2026-10-15']);
        // Job orders earn no leave, so they need no opening; whoever joins after the takeover starts from zero.
        $jobOrder = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);
        ScheduleAssignment::factory()->for($jobOrder)->create(['effective_from' => '2026-01-01']);
        $newcomer = Employee::factory()->create(['date_hired' => '2026-11-02']);
        ScheduleAssignment::factory()->for($newcomer)->create(['effective_from' => '2026-11-02']);

        $this->assertSame([0, 0], [$this->counts()['withoutSchedule'], $this->counts()['withoutOpenings']]);
    }
}
