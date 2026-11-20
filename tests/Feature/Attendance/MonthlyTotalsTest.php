<?php

namespace Tests\Feature\Attendance;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\MonthlyTotals;
use App\Services\Leave\DtrDeduction;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use DateInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class MonthlyTotalsTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        // All of October has happened.
        $this->travelTo('2026-11-03 09:00');
        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
    }

    /**
     * Someone in Nursing on 8–12 and 1–5, Monday to Friday.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function staff(array $attributes = [], string $scheduleFrom = '2026-09-01'): Employee
    {
        $employee = Employee::factory()->for($this->nursing)->create(['middle_name' => null, ...$attributes]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => $scheduleFrom]);

        return $employee;
    }

    /** A DTR for $from to $to with every weekday tapped in full, arriving at $arrival. */
    private function upload(Employee $employee, string $from, string $to, string $arrival = '08:00'): void
    {
        $upload = DtrUpload::factory()->for($employee)->create(['period_start' => $from, 'period_end' => $to]);

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if ($date->isWeekday()) {
                $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => $arrival, 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
            }
        }
    }

    /** @return array<int, array{employee: Employee, dtr: ComputedDtr|null, notes: list<string>}> employee id → row */
    private function october(): array
    {
        return collect(app(MonthlyTotals::class)->rows($this->nursing, CarbonImmutable::parse('2026-10-01')))
            ->keyBy(fn (array $row): int => $row['employee']->id)
            ->all();
    }

    public function test_a_months_dtrs_add_up_and_are_converted_once(): void
    {
        $ana = $this->staff();
        $this->upload($ana, '2026-10-01', '2026-10-01', '08:30');
        $this->upload($ana, '2026-10-02', '2026-10-02', '08:30');

        $dtr = $this->october()[$ana->id]['dtr'];

        // An hour is 0.125 by the table; each half hour converted apart would give 0.124.
        $this->assertSame([60, 2, '0.125'], [$dtr->late, $dtr->lateCount, $dtr->dayEquivalent]);
    }

    public function test_only_the_days_of_the_month_count(): void
    {
        $ana = $this->staff();
        $this->upload($ana, '2026-09-30', '2026-10-01', '08:10');

        $dtr = $this->october()[$ana->id]['dtr'];

        $this->assertSame([10, 1], [$dtr->late, $dtr->lateCount]);
    }

    public function test_staff_without_a_dtr_for_the_month_are_listed_without_figures(): void
    {
        $ana = $this->staff();
        $ben = $this->staff();
        $this->upload($ben, '2026-09-01', '2026-09-30');

        $rows = $this->october();

        $this->assertSame([null, ['No DTR uploaded']], [$rows[$ana->id]['dtr'], $rows[$ana->id]['notes']]);
        $this->assertNull($rows[$ben->id]['dtr']);
    }

    public function test_only_the_offices_active_staff_who_earn_leave_credits_are_listed(): void
    {
        $permanent = $this->staff();
        $coterminous = $this->staff(['employment_status' => EmploymentStatus::Coterminous]);
        $this->staff(['employment_status' => EmploymentStatus::JobOrder]);
        $this->staff(['is_active' => false]);
        Employee::factory()->create();

        $this->assertEqualsCanonicalizing([$permanent->id, $coterminous->id], array_keys($this->october()));
    }

    public function test_without_an_office_every_offices_staff_are_listed_by_surname(): void
    {
        $santos = $this->staff(['last_name' => 'Santos']);
        $abad = Employee::factory()->create(['last_name' => 'Abad']);

        $rows = app(MonthlyTotals::class)->rows(null, CarbonImmutable::parse('2026-10-01'));

        $this->assertSame([$abad->id, $santos->id], array_map(fn (array $row): int => $row['employee']->id, $rows));
    }

    public function test_ten_lates_in_a_month_are_noted_and_nine_are_not(): void
    {
        $ana = $this->staff();
        $ben = $this->staff();
        // October 1–14 has ten work days; October 1–13 has nine.
        $this->upload($ana, '2026-10-01', '2026-10-14', '08:05');
        $this->upload($ben, '2026-10-01', '2026-10-13', '08:05');

        $rows = $this->october();

        $this->assertSame(['10+ lates'], $rows[$ana->id]['notes']);
        $this->assertSame([], $rows[$ben->id]['notes']);
    }

    public function test_days_without_a_schedule_are_noted(): void
    {
        $ana = $this->staff(scheduleFrom: '2026-10-02');
        $this->upload($ana, '2026-10-01', '2026-10-02');

        $this->assertSame(['1 day without a schedule'], $this->october()[$ana->id]['notes']);
    }

    public function test_the_sheet_has_a_row_for_every_employee_of_every_office(): void
    {
        $ana = $this->staff(['last_name' => 'Abad', 'first_name' => 'Ana', 'employee_number' => '2019-0001']);
        $this->staff(['last_name' => 'Bautista', 'first_name' => 'Ben', 'employee_number' => '2019-0002']);
        Employee::factory()->for(Office::factory()->create(['name' => 'Pharmacy']))
            ->create(['last_name' => 'Cruz', 'first_name' => 'Carlo', 'middle_name' => null, 'employee_number' => '2019-0003']);
        $this->upload($ana, '2026-10-01', '2026-10-01', '08:30');

        // Late and undertime are Excel durations, read back as intervals: shown here as "h:mm".
        $value = fn (Cell $cell): mixed => $cell->getValue() instanceof DateInterval ? $cell->getValue()->format('%h:%I') : $cell->getValue();
        $rows = array_map(fn (array $cells): array => array_map($value, $cells), $this->octoberSheet());

        // Numbers stay numbers, so HR can add them up in Excel. Half an hour is 0:30, and 0.062 day by the table.
        // The three without-pay columns are numbers too, 0 when there is none.
        $this->assertContains(['Abad, Ana', '2019-0001', 'Nursing Service', 1, '0:30', 0, '0:00', 0, 0.062, 0, 0, 0, ''], $rows);
        $this->assertContains(['Bautista, Ben', '2019-0002', 'Nursing Service', '', '', '', '', '', '', 0, 0, 0, 'No DTR uploaded'], $rows);
        $this->assertContains(['Cruz, Carlo', '2019-0003', 'Pharmacy', '', '', '', '', '', '', 0, 0, 0, 'No DTR uploaded'], $rows);
    }

    public function test_a_name_that_starts_with_an_equals_sign_stays_text_in_the_sheet(): void
    {
        $this->staff(['last_name' => '=HYPERLINK("http://example.test")', 'first_name' => 'Ana', 'employee_number' => '=1+1']);
        $path = tempnam(sys_get_temp_dir(), 'summary');
        app(MonthlyTotals::class)->writeXlsx($this->nursing, CarbonImmutable::parse('2026-10-01'), $path);

        // OpenSpout's reader makes such text a formula again, so the sheet's XML is read instead.
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('=1+1', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
    }

    /** @return list<list<Cell>> October's sheet for all offices, read back cell by cell */
    private function octoberSheet(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'summary');
        app(MonthlyTotals::class)->writeXlsx(null, CarbonImmutable::parse('2026-10-01'), $path);

        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->getCells();
            }
        }

        $reader->close();
        unlink($path);

        return $rows;
    }

    public function test_rows_carry_the_days_without_pay_from_the_dtr_and_from_leave(): void
    {
        config(['leave.start_date' => '2026-10-01']);
        $ana = $this->staff();
        // 6 minutes late on each of October's 22 weekdays: 132 minutes, 0.275 day, of which 0.075 is more than her 0.200 VL.
        $this->upload($ana, '2026-10-01', '2026-10-31', '08:06');
        LeaveLedgerEntry::factory()->for($ana)->create(['leave_type' => LeaveType::Vacation, 'days' => '0.200', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-09-30']);
        app(DtrDeduction::class)->finalize(DtrUpload::query()->whereBelongsTo($ana)->sole(), User::factory()->create());
        LeaveApplication::factory()->for($ana)->create([
            'date_from' => '2026-10-17', 'date_to' => '2026-10-17', 'days_with_pay' => '0.000', 'days_without_pay' => '1.000',
            'breakdown' => ['2026-10-17' => ['days' => '1.000', 'without_pay' => '1.000']],
        ]);

        $this->assertSame(['dtr' => '0.075', 'leave' => '1.000', 'total' => '1.075'], $this->october()[$ana->id]['withoutPay']);

        // The title, then the header: the reader skips the empty row between.
        $header = array_map(fn (Cell $cell): mixed => $cell->getValue(), $this->octoberSheet()[1]);
        $this->assertSame(['Without pay: DTR', 'Without pay: leave', 'Without pay: total'], array_slice($header, 9, 3));
    }

    public function test_staff_separated_in_or_after_the_month_still_have_a_row(): void
    {
        // Resigned October 31 and set inactive since: October's row is the one payroll needs for the final pay.
        $resigned = $this->staff(['date_separated' => '2026-10-31', 'is_active' => false]);
        $gone = $this->staff(['date_separated' => '2026-09-30', 'is_active' => false]);

        $this->assertArrayHasKey($resigned->id, $this->october());
        $this->assertArrayNotHasKey($gone->id, $this->october());
    }
}
