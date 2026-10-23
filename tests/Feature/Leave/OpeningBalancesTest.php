<?php

namespace Tests\Feature\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\User;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\OpeningBalances;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Employee $juan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->office = Office::factory()->create();
        $this->juan = Employee::factory()->for($this->office)->create();
    }

    public function test_saving_writes_opening_rows_dated_the_day_before_the_start(): void
    {
        $this->assertSame(5, $this->save([$this->juan->id => ['VL' => '12.5', 'SL' => '30.125', 'SPL' => '2', 'WL' => '5', 'FL' => '1']]));

        $vacation = $this->juan->leaveLedgerEntries()->where('leave_type', 'VL')->sole();
        $this->assertSame('12.500', $vacation->days);
        $this->assertSame('2026-10-31', $vacation->posted_on->toDateString());
        $this->assertSame(LedgerKind::Opening, $vacation->kind);
        $this->assertSame("opening:{$this->juan->id}:VL", $vacation->posting_key);
        $this->assertSame(auth()->id(), $vacation->created_by_user_id);
    }

    public function test_a_blank_or_zero_cell_writes_no_row(): void
    {
        $this->save([$this->juan->id => ['VL' => '10', 'SL' => '', 'SPL' => '0', 'WL' => '', 'FL' => '']]);

        $this->assertSame(1, $this->juan->leaveLedgerEntries()->count());
    }

    public function test_saving_again_replaces_the_opening_rows_and_logs_the_old_value(): void
    {
        $this->save([$this->juan->id => ['VL' => '10', 'SL' => '5']]);

        $this->assertSame(2, $this->save([$this->juan->id => ['VL' => '11', 'SL' => '']]));

        $this->assertSame(['VL' => '11.000', 'SL' => '0.000', 'SPL' => '0.000', 'WL' => '0.000'], app(LeaveLedger::class)->balances($this->juan));
        $this->assertSame('10.000', Activity::query()->where('event', 'updated')->sole()->attribute_changes['old']['days']);
    }

    public function test_a_bad_value_saves_nothing_and_names_the_cell(): void
    {
        foreach (['1.2505', 'abc', '-1', '1000'] as $typed) {
            $this->assertSaveRefused([$this->juan->id => ['VL' => '10', 'SL' => $typed]], "rows.{$this->juan->id}.SL");
        }

        $this->assertSaveRefused([$this->juan->id => ['SPL' => '4']], "rows.{$this->juan->id}.SPL");
        $this->assertSaveRefused([$this->juan->id => ['WL' => '5.5']], "rows.{$this->juan->id}.WL");
        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_a_first_save_is_accepted_after_credits_have_posted(): void
    {
        LeaveLedgerEntry::factory()->for($this->juan)->create(['kind' => LedgerKind::MonthlyCredit, 'posted_on' => '2026-12-01']);

        $this->assertSame(1, $this->save([$this->juan->id => ['VL' => '10']]));
    }

    public function test_a_resave_is_refused_once_the_employee_has_other_rows(): void
    {
        $this->save([$this->juan->id => ['VL' => '10']]);
        LeaveLedgerEntry::factory()->for($this->juan)->create(['kind' => LedgerKind::MonthlyCredit, 'posted_on' => '2026-12-01']);

        $this->assertSaveRefused([$this->juan->id => ['VL' => '11']], "rows.{$this->juan->id}.VL");
    }

    public function test_employees_outside_the_office_or_without_credits_are_ignored(): void
    {
        $other = Employee::factory()->create();
        $jobOrder = Employee::factory()->for($this->office)->create(['employment_status' => EmploymentStatus::JobOrder]);

        $this->assertSame(0, $this->save([$other->id => ['VL' => '10'], $jobOrder->id => ['VL' => '10']]));
        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    /** @param  array<int, array<string, string>>  $rows */
    private function save(array $rows): int
    {
        return app(OpeningBalances::class)->save($this->office, CarbonImmutable::parse('2026-11-01'), $rows);
    }

    /** @param  array<int, array<string, string>>  $rows */
    private function assertSaveRefused(array $rows, string $errorKey): void
    {
        try {
            $this->save($rows);
            $this->fail("Saved without an error on {$errorKey}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
        }
    }
}
