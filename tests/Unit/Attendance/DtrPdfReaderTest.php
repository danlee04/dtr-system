<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\DtrPdfReader;
use App\Services\Attendance\PrintedDtr;
use App\Services\Attendance\UnreadableDtr;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DtrPdf;

class DtrPdfReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->files);

        parent::tearDown();
    }

    /** @return list<PrintedDtr> */
    private function read(string $contents): array
    {
        $this->files[] = $path = tempnam(sys_get_temp_dir(), 'dtr');
        file_put_contents($path, $contents);

        return (new DtrPdfReader)->read($path);
    }

    private function assertUnreadable(string $contents, string $message): void
    {
        try {
            $this->read($contents);
            $this->fail('The file was read.');
        } catch (UnreadableDtr $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_reads_the_name_the_month_and_each_days_times_by_column(): void
    {
        $pdf = (new DtrPdf('DELA CRUZ, JUAN S.', 'OCTOBER 1 - 31, 2026'))
            ->day(1, ['am_arrival' => '07:51 am', 'am_departure' => '12:25 pm', 'pm_arrival' => '12:44 pm', 'pm_departure' => '05:13 pm'])
            ->day(2, ['am_arrival' => '07:47 am', 'am_departure' => '12:05 pm', 'pm_departure' => '05:05 pm'])
            ->day(19, ['am_arrival' => '07:48 am', 'pm_departure' => '07:00 pm'])
            ->day(21, ['ot_arrival' => '05:30 pm', 'ot_departure' => '09:00 pm']);

        [$dtr] = $this->read($pdf->contents());

        $this->assertSame('DELA CRUZ, JUAN S.', $dtr->employeeName);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$dtr->periodStart->toDateString(), $dtr->periodEnd->toDateString()]);
        $this->assertSame([
            '2026-10-01' => ['am_arrival' => '07:51', 'am_departure' => '12:25', 'pm_arrival' => '12:44', 'pm_departure' => '17:13'],
            '2026-10-02' => ['am_arrival' => '07:47', 'am_departure' => '12:05', 'pm_departure' => '17:05'],
            '2026-10-19' => ['am_arrival' => '07:48', 'pm_departure' => '19:00'],
            '2026-10-21' => ['ot_arrival' => '17:30', 'ot_departure' => '21:00'],
        ], $dtr->days);
    }

    public function test_twelve_oclock_reads_as_noon_or_midnight(): void
    {
        $pdf = (new DtrPdf)->day(9, ['pm_arrival' => '12:00 pm', 'ot_departure' => '12:30 am']);

        $this->assertSame(['pm_arrival' => '12:00', 'ot_departure' => '00:30'], $this->read($pdf->contents())[0]->days['2026-10-09']);
    }

    public function test_reads_only_the_days_of_the_printed_period(): void
    {
        $pdf = (new DtrPdf(month: 'OCTOBER 1 - 15, 2026'))
            ->day(3, ['am_arrival' => '07:50 am'])
            ->day(20, ['am_arrival' => '07:55 am']);

        $dtr = $this->read($pdf->contents())[0];

        $this->assertSame('2026-10-15', $dtr->periodEnd->toDateString());
        $this->assertSame(['2026-10-03'], array_keys($dtr->days));
    }

    public function test_keeps_remarks_but_not_the_stray_dash(): void
    {
        // The IT Unit's software prints an invisible "-" in some rows.
        $pdf = (new DtrPdf)
            ->day(5, ['am_arrival' => '07:49 am', 'remarks' => '-'])
            ->day(26, ['remarks' => 'OB']);

        $this->assertSame([
            '2026-10-05' => ['am_arrival' => '07:49'],
            '2026-10-26' => ['remarks' => 'OB'],
        ], $this->read($pdf->contents())[0]->days);
    }

    public function test_reads_every_page_as_its_own_dtr(): void
    {
        $dtrs = $this->read(DtrPdf::pages(new DtrPdf('DELA CRUZ, JUAN S.'), new DtrPdf('SANTOS, ANA')));

        $this->assertSame(['DELA CRUZ, JUAN S.', 'SANTOS, ANA'], array_map(fn (PrintedDtr $dtr): string => $dtr->employeeName, $dtrs));
    }

    public function test_rejects_a_pdf_that_is_not_a_dtr(): void
    {
        $this->assertUnreadable(DtrPdf::document('Payslip for October'), 'It has no "(Employee Name)" line, so it is not a DTR printed by the IT Unit.');
    }

    public function test_names_the_page_that_is_not_a_dtr(): void
    {
        $this->assertUnreadable(
            DtrPdf::pages(new DtrPdf, new DtrPdf(month: 'OCTOBER 2026')),
            'Page 2: Its month reads "OCTOBER 2026", not a month such as "OCTOBER 1 - 31, 2026".',
        );
    }

    public function test_rejects_a_month_it_cannot_read(): void
    {
        $this->assertUnreadable((new DtrPdf(month: 'OCTOBER 2026'))->contents(), 'Its month reads "OCTOBER 2026", not a month such as "OCTOBER 1 - 31, 2026".');
    }

    public function test_rejects_a_file_that_is_not_a_pdf(): void
    {
        $this->assertUnreadable("employee_number,last_name\n", 'It is not a PDF the app can open.');
    }

    public function test_reads_the_it_units_own_samples(): void
    {
        // Real DTRs carry real names and times, so they stay out of the
        // repository: this runs on a machine that has them in samples/.
        $samples = glob(dirname(__DIR__, 3).'/storage/app/samples/*.pdf') ?: [];

        if ($samples === []) {
            $this->markTestSkipped('No DTR samples in storage/app/samples.');
        }

        foreach ($samples as $sample) {
            foreach ((new DtrPdfReader)->read($sample) as $dtr) {
                $this->assertNotSame('', $dtr->employeeName, $sample);
                $this->assertNotSame([], $dtr->days, $sample);
                $this->assertSame([], array_filter(
                    array_keys($dtr->days),
                    fn (string $date): bool => $date < $dtr->periodStart->toDateString() || $date > $dtr->periodEnd->toDateString(),
                ), $sample);
            }
        }
    }
}
