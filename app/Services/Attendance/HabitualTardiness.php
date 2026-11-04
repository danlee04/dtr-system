<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Habitual tardiness under CSC rules, from the DTRs uploaded: late ten or more
 * times in a month, in two months of the same half year, or in two months in
 * a row of the same year. June and July run on across the halves, so they
 * count in either; December and the next January are different years and do
 * not. Every late counts once: late in the morning and again after lunch is
 * two. The basis for HR's memo; the app does not write the memo.
 */
final class HabitualTardiness
{
    /** Lates in a month that count toward the rule. */
    public const int LATES = MonthlyTotals::FREQUENT_LATES;

    public function __construct(private MonthlyTotals $monthlyTotals) {}

    /**
     * The six months of a half year: 1 is January to June, 2 July to December.
     *
     * @return list<CarbonImmutable>
     */
    public static function months(int $year, int $half): array
    {
        $first = CarbonImmutable::create($year, $half === 1 ? 1 : 7);

        return array_map(fn (int $offset): CarbonImmutable => $first->addMonths($offset), range(0, 5));
    }

    /** "January–June 2026" or "July–December 2026". */
    public static function label(int $year, int $half): string
    {
        return ($half === 1 ? 'January–June' : 'July–December')." {$year}";
    }

    /**
     * "Feb and Apr", from ["2026-02", "2026-04"].
     *
     * @param  list<string>  $months  as "Y-m"
     */
    public static function monthNames(array $months): string
    {
        return Arr::join(array_map(fn (string $month): string => CarbonImmutable::parse("{$month}-01")->format('M'), $months), ', ', ' and ');
    }

    /**
     * Everyone with a month of ten or more lates in the half year, surname
     * first: each month's lates (null where no DTR covers the month), and the
     * months that make them habitual (none when they are not).
     *
     * @return list<array{employee: Employee, lates: array<string, int|null>, habitual: list<string>}> months keyed "Y-m"
     */
    public function semester(int $year, int $half): array
    {
        $months = self::months($year, $half);
        // The month on the other side of June and July, which run on across the halves.
        $neighbour = $half === 1 ? $months[5]->addMonth() : $months[0]->subMonth();
        $byMonth = [];

        foreach ([...$months, $neighbour] as $month) {
            $byMonth[$month->format('Y-m')] = $this->lates($month);
        }

        $keys = array_map(fn (CarbonImmutable $month): string => $month->format('Y-m'), $months);
        $listed = collect($keys)
            ->flatMap(fn (string $key): array => array_keys(array_filter($byMonth[$key], fn (int $lates): bool => $lates >= self::LATES)))
            ->unique();

        return Employee::query()
            ->whereKey($listed->all())
            ->with('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($keys, $byMonth, $year): array {
                $lates = array_combine($keys, array_map(fn (string $key): ?int => $byMonth[$key][$employee->id] ?? null, $keys));
                $frequent = array_keys(array_filter($lates, fn (?int $count): bool => $count !== null && $count >= self::LATES));
                $runOn = ($byMonth["{$year}-06"][$employee->id] ?? 0) >= self::LATES && ($byMonth["{$year}-07"][$employee->id] ?? 0) >= self::LATES;

                return [
                    'employee' => $employee,
                    'lates' => $lates,
                    'habitual' => match (true) {
                        count($frequent) >= 2 => $frequent,
                        $runOn => ["{$year}-06", "{$year}-07"],
                        default => [],
                    },
                ];
            })
            ->all();
    }

    /** The list as an Excel sheet, written to $path; 'php://output' sends it. */
    public function writeXlsx(int $year, int $half, string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(32, 1);
        $options->setColumnWidth(14, 2);
        $options->setColumnWidth(28, 3);
        $options->setColumnWidthForRange(8, 4, 9);
        $options->setColumnWidth(10, 10);
        $options->setColumnWidth(24, 11);

        $bold = (new Style)->setFontBold();
        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(new Row([new StringCell('Habitual tardiness: '.self::label($year, $half), null)], $bold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Employee', 'Employee No.', 'Office',
            ...array_map(fn (CarbonImmutable $month): string => $month->format('M'), self::months($year, $half)),
            'Habitual', 'Months',
        ], $bold));

        foreach ($this->semester($year, $half) as ['employee' => $employee, 'lates' => $lates, 'habitual' => $habitual]) {
            // Text goes in as text: OpenSpout reads anything starting with "=" as a formula, even a name.
            $writer->addRow(new Row([
                new StringCell($employee->full_name, null),
                new StringCell($employee->employee_number, null),
                new StringCell($employee->office->name, null),
                ...array_map(Cell::fromValue(...), array_values($lates)),
                new StringCell($habitual !== [] ? 'Yes' : '', null),
                new StringCell(self::monthNames($habitual), null),
            ]));
        }

        $writer->close();
    }

    /**
     * Each employee's lates in the month, for those with a DTR covering it.
     * Kept ten minutes, since it computes every DTR of the month.
     *
     * shortcut: a cold half year computes up to seven months of DTRs, about a second each at 100 DTRs; store the monthly figures if this page gets slow.
     *
     * @return array<int, int> employee id → lates
     */
    private function lates(CarbonImmutable $month): array
    {
        return Cache::remember("tardiness.lates.{$month->format('Y-m')}", now()->addMinutes(10), fn (): array => collect($this->monthlyTotals->rows(null, $month))
            ->filter(fn (array $row): bool => $row['dtr'] !== null)
            ->mapWithKeys(fn (array $row): array => [$row['employee']->id => $row['dtr']->lateCount])
            ->all());
    }
}
