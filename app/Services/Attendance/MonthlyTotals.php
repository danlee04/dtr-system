<?php

namespace App\Services\Attendance;

use App\Enums\EmploymentStatus;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * A month of late, undertime and absences, a row per employee, from the DTRs
 * uploaded for that month, for every office or one: what the Monthly Summary
 * page shows and the Excel sheet HR downloads from it.
 */
final class MonthlyTotals
{
    /** Lates in a month worth a look: ten a month, two months running, is habitual tardiness under CSC rules. */
    public const int FREQUENT_LATES = 10;

    /**
     * The active staff who earn leave credits, of one office or of all,
     * surname first, each with the month's figures (null when no DTR covers
     * the month) and what HR should look at. Only their DTRs are computed.
     *
     * shortcut: lists today's active staff, so someone separated during the month drops off; add them back if HR reports on them.
     * shortcut: computes every DTR of the month on each call; keep the results if all offices at once gets slow.
     *
     * @return list<array{employee: Employee, dtr: ComputedDtr|null, notes: list<string>}>
     */
    public function rows(?Office $office, CarbonImmutable $month): array
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        $employees = Employee::query()
            ->when($office, fn (Builder $query, Office $office): Builder => $query->whereBelongsTo($office))
            ->whereIn('employment_status', $earning)
            ->where('is_active', true)
            ->with('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $uploads = $this->uploads($employees->modelKeys(), $month);

        return $employees->map(function (Employee $employee) use ($uploads, $month): array {
            if (! $uploads->has($employee->id)) {
                return ['employee' => $employee, 'dtr' => null, 'notes' => ['No DTR uploaded']];
            }

            $dtr = $this->combine($uploads->get($employee->id), $month);

            return ['employee' => $employee, 'dtr' => $dtr, 'notes' => $this->notes($dtr)];
        })->all();
    }

    /** One employee's month, counted as their row is; null when no DTR covers it. */
    public function dtr(Employee $employee, CarbonImmutable $month): ?ComputedDtr
    {
        $uploads = $this->uploads([$employee->id], $month);

        return $uploads->has($employee->id) ? $this->combine($uploads->get($employee->id), $month) : null;
    }

    /** The rows as an Excel sheet, written to $path; 'php://output' sends it. */
    public function writeXlsx(?Office $office, CarbonImmutable $month, string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(32, 1);
        $options->setColumnWidth(14, 2);
        $options->setColumnWidth(28, 3);
        $options->setColumnWidthForRange(12, 4, 9);
        $options->setColumnWidth(40, 10);

        $bold = (new Style)->setFontBold();
        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->addRow(new Row([new StringCell('Monthly summary: '.($office->name ?? 'All offices').", {$month->format('F Y')}", null)], $bold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Employee', 'Employee No.', 'Office', 'Times late', 'Minutes late', 'Times undertime', 'Minutes undertime', 'Absent (days)', 'Equivalent (days)', 'Notes'], $bold));

        foreach ($this->rows($office, $month) as ['employee' => $employee, 'dtr' => $dtr, 'notes' => $notes]) {
            // Text goes in as text: OpenSpout reads anything starting with "=" as a formula, even a name.
            $writer->addRow(new Row([
                new StringCell($employee->full_name, null),
                new StringCell($employee->employee_number, null),
                new StringCell($employee->office->name, null),
                ...array_map(Cell::fromValue(...), [
                    $dtr?->lateCount,
                    $dtr?->late,
                    $dtr?->undertimeCount,
                    $dtr?->undertime,
                    $dtr?->absent,
                    $dtr !== null ? (float) $dtr->dayEquivalent : null,
                ]),
                new StringCell(implode('; ', $notes), null),
            ]));
        }

        $writer->close();
    }

    /**
     * The DTRs that cover the month, by employee id.
     *
     * @param  list<int>  $employeeIds
     * @return Collection<int, Collection<int, DtrUpload>>
     */
    private function uploads(array $employeeIds, CarbonImmutable $month): Collection
    {
        return DtrUpload::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('period_start', '<=', $month->endOfMonth()->toDateString())
            ->whereDate('period_end', '>=', $month->startOfMonth()->toDateString())
            ->with(['employee', 'days'])
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * One employee's DTRs as one month: two half-month uploads add up, and
     * only the month's own dates count.
     *
     * @param  Collection<int, DtrUpload>  $uploads
     */
    private function combine(Collection $uploads, CarbonImmutable $month): ComputedDtr
    {
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();
        // A newer upload replaces any it overlaps, so the DTRs' dates never collide.
        $days = [];

        foreach ($uploads as $upload) {
            $days += $upload->computed()->days;
        }

        return new ComputedDtr(array_filter($days, fn (string $date): bool => $date >= $from && $date <= $to, ARRAY_FILTER_USE_KEY));
    }

    /** @return list<string> */
    private function notes(ComputedDtr $dtr): array
    {
        return array_values(array_filter([
            $dtr->lateCount >= self::FREQUENT_LATES ? self::FREQUENT_LATES.'+ lates' : null,
            $dtr->daysWithoutSchedule > 0 ? "{$dtr->daysWithoutSchedule} ".str('day')->plural($dtr->daysWithoutSchedule).' without a schedule' : null,
        ]));
    }
}
