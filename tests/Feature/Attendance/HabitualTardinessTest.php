<?php

namespace Tests\Feature\Attendance;

use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Attendance\HabitualTardiness;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class HabitualTardinessTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        // Every month these tests use has happened.
        $this->travelTo('2028-01-15 09:00');
        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
    }

    /**
     * Someone in Nursing on 8–12 and 1–5, Monday to Friday, since 2026.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function staff(array $attributes = []): Employee
    {
        $employee = Employee::factory()->for($this->nursing)->create(['middle_name' => null, ...$attributes]);
        ScheduleAssignment::factory()->for($employee)->for(Schedule::factory())->create(['effective_from' => '2026-01-01']);

        return $employee;
    }

    /** A month's DTR with its first $count weekdays begun five minutes late. */
    private function lates(Employee $employee, string $month, int $count): void
    {
        $first = CarbonImmutable::parse("{$month}-01");
        $upload = DtrUpload::factory()->for($employee)->create(['period_start' => $first, 'period_end' => $first->endOfMonth()]);

        foreach (CarbonPeriod::create($first, $first->endOfMonth()) as $date) {
            if ($date->isWeekday() && $count-- > 0) {
                $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => '08:05', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
            }
        }
    }

    /** @return list<array{employee: Employee, lates: array<string, int|null>, habitual: list<string>}> */
    private function half(int $year, int $half): array
    {
        return app(HabitualTardiness::class)->semester($year, $half);
    }

    public function test_ten_lates_in_two_months_of_the_same_half_year_is_habitual(): void
    {
        $ana = $this->staff();
        $this->lates($ana, '2026-02', 10);
        $this->lates($ana, '2026-04', 12);

        [$row] = $this->half(2026, 1);

        $this->assertSame(['2026-01' => null, '2026-02' => 10, '2026-03' => null, '2026-04' => 12, '2026-05' => null, '2026-06' => null], $row['lates']);
        $this->assertSame(['2026-02', '2026-04'], $row['habitual']);
    }

    public function test_one_such_month_is_listed_but_not_habitual_and_nine_lates_are_not_listed(): void
    {
        $ana = $this->staff();
        $ben = $this->staff();
        $this->lates($ana, '2026-03', 11);
        $this->lates($ben, '2026-03', 9);
        $this->lates($ben, '2026-05', 9);

        $rows = $this->half(2026, 1);

        $this->assertSame([$ana->id], array_map(fn (array $row): int => $row['employee']->id, $rows));
        $this->assertSame([], $rows[0]['habitual']);
    }

    public function test_june_and_july_run_on_so_they_count_in_either_half_year(): void
    {
        $ana = $this->staff();
        $this->lates($ana, '2026-06', 10);
        $this->lates($ana, '2026-07', 10);

        $this->assertSame(['2026-06', '2026-07'], $this->half(2026, 1)[0]['habitual']);
        $this->assertSame(['2026-06', '2026-07'], $this->half(2026, 2)[0]['habitual']);
    }

    public function test_december_and_the_next_january_do_not_pair(): void
    {
        $ana = $this->staff();
        $this->lates($ana, '2026-12', 10);
        $this->lates($ana, '2027-01', 10);

        $this->assertSame([], $this->half(2026, 2)[0]['habitual']);
        $this->assertSame([], $this->half(2027, 1)[0]['habitual']);
    }

    public function test_the_sheet_has_a_row_per_employee_listed(): void
    {
        $ana = $this->staff(['last_name' => 'Abad', 'first_name' => 'Ana', 'employee_number' => '2019-0001']);
        $this->lates($ana, '2026-02', 10);
        $this->lates($ana, '2026-04', 12);
        $path = tempnam(sys_get_temp_dir(), 'habitual');

        app(HabitualTardiness::class)->writeXlsx(2026, 1, $path);

        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn (Cell $cell): mixed => $cell->getValue(), $row->getCells());
            }
        }

        $reader->close();
        unlink($path);

        // January to June, then whether habitual and the months that make it so.
        $this->assertContains(['Abad, Ana', '2019-0001', 'Nursing Service', '', 10, '', 12, '', '', 'Yes', 'Feb and Apr'], $rows);
    }
}
