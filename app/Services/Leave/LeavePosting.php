<?php

namespace App\Services\Leave;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posts what the calendar owes: each month's VL and SL credit on the 1st of
 * the next; on 31 December the forced leave not taken and the SPL and
 * Wellness that lapse; on 1 January the new year's SPL and Wellness. Every
 * row carries a posting key that is unique in the database, so a second run,
 * or two runs at once, post nothing twice, and a PC that was off catches up
 * with each row on the date it was due.
 */
final class LeavePosting
{
    /** VL on 31 December at or above which the forced-leave rule applies. */
    private const string FORCED_LEAVE_THRESHOLD = '10';

    /** The VL days a year an employee must take, or lose. */
    private const string FORCED_LEAVE_DAYS = '5';

    /** @var array<string, string> leave type code → days granted every 1 January */
    private const array YEARLY_GRANT = ['SPL' => '3.000', 'WL' => '5.000'];

    /**
     * LEAVE_START_DATE as a date, or null while it is blank or not the 1st of
     * a month.
     */
    public static function configuredStart(): ?CarbonImmutable
    {
        $start = config('leave.start_date');

        if (! is_string($start) || ! CarbonImmutable::hasFormat($start, 'Y-m-d')) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $start);

        return $date->day === 1 ? $date : null;
    }

    /**
     * The CSC credit for a month: 1.25 × (days of service − days without
     * pay) ÷ 30, to three places, half up. Worked in thousandths of a day,
     * where it is (S − L) ÷ 24, so the rounding is exact.
     */
    public static function creditFor(int $serviceDays, string $daysWithoutPay = '0'): string
    {
        $thousandths = intdiv(max(0, $serviceDays * 1000 - (int) bcmul($daysWithoutPay, '1000', 0)) + 12, 24);

        return bcdiv((string) $thousandths, '1000', 3);
    }

    /**
     * Posts only the periods after the last one posted. Who earns is judged
     * when a period is posted: there is no history of status or of the
     * active flag, so re-judging an old month by today's values would
     * credit a job order appointed in June, or an employee reactivated, for
     * months they did not earn. Later changes go through adjustments.
     *
     * Monthly credits first, so a catch-up run has December's credit in
     * place before it reads the balance on 31 December. The start date's own
     * year has no grant: its SPL and Wellness come from the opening balance.
     * Each period is posted in one transaction, so a run that dies midway
     * never leaves a period half posted behind the mark.
     *
     * @return int the number of rows inserted
     */
    public function post(CarbonImmutable $start, CarbonImmutable $today): int
    {
        $employees = $this->employees();
        $posted = 0;

        for ($month = $this->firstUnpostedMonth($start); $month->addMonth()->lessThanOrEqualTo($today); $month = $month->addMonth()) {
            $posted += DB::transaction(fn (): int => $this->postMonthlyCredits($employees, $month));
        }

        for ($year = $this->firstOpenYear($start); CarbonImmutable::create($year + 1, 1, 1)->lessThanOrEqualTo($today); $year++) {
            $posted += DB::transaction(fn (): int => $this->postYearEnd($employees, $year) + $this->postYearlyGrant($employees, $year + 1));
        }

        return $posted;
    }

    /**
     * Posts what is due today, when LEAVE_START_DATE is set. Anything that
     * checks a balance calls it first, so the PC's first hour after being
     * off never checks against a ledger that is missing a 1st.
     */
    public function catchUp(): int
    {
        $start = self::configuredStart();

        return $start === null ? 0 : $this->post($start, CarbonImmutable::today());
    }

    /** A year is closed once its end is posted, which is when the next year's grant is. */
    public function isYearClosed(int $year): bool
    {
        return LeaveLedgerEntry::query()
            ->where('kind', LedgerKind::YearlyGrant)
            ->where('period', '>', (string) $year)
            ->exists();
    }

    /** The month after the last credited one, never before the start. */
    private function firstUnpostedMonth(CarbonImmutable $start): CarbonImmutable
    {
        $last = LeaveLedgerEntry::query()->where('kind', LedgerKind::MonthlyCredit)->max('period');

        return $last === null
            ? $start->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $last)->addMonth()->max($start->startOfMonth());
    }

    /** A grant for year G is posted with year G − 1's end, so G is the first open year. */
    private function firstOpenYear(CarbonImmutable $start): int
    {
        $last = LeaveLedgerEntry::query()->where('kind', LedgerKind::YearlyGrant)->max('period');

        return $last === null ? $start->year : max((int) $last, $start->year);
    }

    /**
     * Forced leave and the lapse, dated 31 December and read from the
     * balances on that date, whichever day the run happens.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function postYearEnd(Collection $employees, int $year): int
    {
        $lastDay = CarbonImmutable::create($year, 12, 31);
        $balances = $this->balancesOn($lastDay);
        $taken = $this->vlTakenIn($year);
        $period = (string) $year;
        $rows = [];

        foreach ($employees as $employee) {
            if (! $this->earnsOn($employee, $lastDay)) {
                continue;
            }

            $vacation = $balances[$employee->id][LeaveType::Vacation->value] ?? '0';
            $shortfall = bcsub(self::FORCED_LEAVE_DAYS, $taken[$employee->id] ?? '0', 3);

            if (bccomp($vacation, self::FORCED_LEAVE_THRESHOLD, 3) >= 0 && bccomp($shortfall, '0', 3) > 0) {
                $rows[] = $this->row(
                    $employee, LeaveType::Vacation, $lastDay, bcsub('0', $shortfall, 3),
                    LedgerKind::ForcedLeave, $period, "forced:{$employee->id}:{$year}",
                );
            }

            foreach ([LeaveType::SpecialPrivilege, LeaveType::Wellness] as $type) {
                $remaining = $balances[$employee->id][$type->value] ?? '0';

                if (bccomp($remaining, '0', 3) > 0) {
                    $rows[] = $this->row(
                        $employee, $type, $lastDay, bcsub('0', $remaining, 3),
                        LedgerKind::Lapse, $period, "lapse:{$employee->id}:{$type->value}:{$year}",
                    );
                }
            }
        }

        return $this->insert($rows);
    }

    /** @param  Collection<int, Employee>  $employees */
    private function postYearlyGrant(Collection $employees, int $year): int
    {
        $firstDay = CarbonImmutable::create($year, 1, 1);
        $rows = [];

        foreach ($employees as $employee) {
            if (! $this->earnsOn($employee, $firstDay)) {
                continue;
            }

            foreach (self::YEARLY_GRANT as $code => $days) {
                $rows[] = $this->row(
                    $employee, LeaveType::from($code), $firstDay, $days,
                    LedgerKind::YearlyGrant, (string) $year, "grant:{$employee->id}:{$code}:{$year}",
                );
            }
        }

        return $this->insert($rows);
    }

    /**
     * Every employee's balances on a date, in one query.
     *
     * @return array<int, array<string, string>> employee id → leave type code → days
     */
    private function balancesOn(CarbonImmutable $date): array
    {
        $balances = [];

        $totals = LeaveLedgerEntry::query()
            ->whereDate('posted_on', '<=', $date)
            ->selectRaw('employee_id, leave_type, sum(days) as total')
            ->groupBy('employee_id', 'leave_type')
            ->toBase()
            ->get();

        foreach ($totals as $total) {
            $balances[$total->employee_id][$total->leave_type] = number_format((float) $total->total, 3, '.', '');
        }

        return $balances;
    }

    /**
     * The VL days each employee took in the year, toward the five: the VL
     * drawn by VL and forced-leave applications that start in the year and
     * are not cancelled, plus the opening FL row (the days taken before the
     * system started).
     *
     * @return array<int, string> employee id → days
     */
    private function vlTakenIn(int $year): array
    {
        $openings = LeaveLedgerEntry::query()
            ->where('kind', LedgerKind::Opening)
            ->where('leave_type', LeaveType::Forced)
            ->whereYear('posted_on', $year)
            ->selectRaw('employee_id, sum(days) as total')
            ->groupBy('employee_id')
            ->toBase()
            ->pluck('total', 'employee_id');

        $drawn = LeaveLedgerEntry::query()
            ->join('leave_applications', 'leave_applications.id', '=', 'leave_ledger_entries.leave_application_id')
            ->where('leave_ledger_entries.kind', LedgerKind::Application)
            ->where('leave_ledger_entries.leave_type', LeaveType::Vacation)
            ->whereIn('leave_applications.leave_type', [LeaveType::Vacation, LeaveType::Forced])
            ->where('leave_applications.status', '!=', LeaveApplicationStatus::Cancelled)
            ->whereYear('leave_applications.date_from', $year)
            ->selectRaw('leave_ledger_entries.employee_id, sum(leave_ledger_entries.days) as total')
            ->groupBy('leave_ledger_entries.employee_id')
            ->toBase()
            ->pluck('total', 'employee_id');

        $taken = [];

        foreach ($openings as $employeeId => $total) {
            $taken[$employeeId] = number_format((float) $total, 3, '.', '');
        }

        // Application rows are negative: the days drawn.
        foreach ($drawn as $employeeId => $total) {
            $taken[$employeeId] = bcsub($taken[$employeeId] ?? '0', number_format((float) $total, 3, '.', ''), 3);
        }

        return $taken;
    }

    /**
     * Each employee's days without pay dated in the month, from applications
     * that are not cancelled, read from their breakdowns.
     *
     * @return array<int, string> employee id → days
     */
    private function daysWithoutPayIn(CarbonImmutable $month, ?int $employeeId = null): array
    {
        $prefix = $month->format('Y-m');
        $totals = [];

        $applications = LeaveApplication::query()
            ->where('status', '!=', LeaveApplicationStatus::Cancelled)
            ->where('days_without_pay', '>', 0)
            ->whereDate('date_from', '<=', $month->endOfMonth())
            ->whereDate('date_to', '>=', $month->startOfMonth())
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->get(['employee_id', 'breakdown']);

        foreach ($applications as $application) {
            foreach ($application->breakdown as $date => $day) {
                if (str_starts_with($date, $prefix)) {
                    $totals[$application->employee_id] = bcadd($totals[$application->employee_id] ?? '0', $day['without_pay'], 3);
                }
            }
        }

        return $totals;
    }

    /**
     * Brings the credit of months already posted in line with the leave
     * without pay filed or cancelled since: the difference becomes a
     * correction row for VL and SL, dated today. Only a month the employee
     * was credited is corrected, since who earns is judged when a period is
     * posted.
     *
     * @param  list<string>  $months  Y-m
     * @return int the number of rows inserted
     */
    public function correctCredits(Employee $employee, array $months): int
    {
        $rows = [];

        foreach (array_unique($months) as $period) {
            $credited = LeaveLedgerEntry::query()
                ->whereBelongsTo($employee)
                ->where('leave_type', LeaveType::Vacation)
                ->where('period', $period)
                ->whereIn('kind', [LedgerKind::MonthlyCredit, LedgerKind::CreditCorrection]);

            if (! (clone $credited)->where('kind', LedgerKind::MonthlyCredit)->exists()) {
                continue;
            }

            $month = CarbonImmutable::createFromFormat('!Y-m', $period);
            $owed = self::creditFor($this->serviceDays($employee, $month), $this->daysWithoutPayIn($month, $employee->id)[$employee->id] ?? '0');
            $difference = bcsub($owed, number_format((float) $credited->sum('days'), 3, '.', ''), 3);

            if (bccomp($difference, '0', 3) === 0) {
                continue;
            }

            foreach ([LeaveType::Vacation, LeaveType::Sick] as $type) {
                $rows[] = $this->row(
                    $employee, $type, CarbonImmutable::today(), $difference,
                    LedgerKind::CreditCorrection, $period, null, "Correction for {$month->format('F Y')}",
                );
            }
        }

        return $this->insert($rows);
    }

    private function earnsOn(Employee $employee, CarbonImmutable $date): bool
    {
        return ($employee->date_hired === null || $employee->date_hired->lessThanOrEqualTo($date))
            && ($employee->date_separated === null || $employee->date_separated->greaterThanOrEqualTo($date));
    }

    /** @param  Collection<int, Employee>  $employees */
    private function postMonthlyCredits(Collection $employees, CarbonImmutable $month): int
    {
        $period = $month->format('Y-m');
        $withoutPay = $this->daysWithoutPayIn($month);
        $rows = [];

        foreach ($employees as $employee) {
            $serviceDays = $this->serviceDays($employee, $month);

            if ($serviceDays === 0) {
                continue;
            }

            $credit = self::creditFor($serviceDays, $withoutPay[$employee->id] ?? '0');

            foreach ([LeaveType::Vacation, LeaveType::Sick] as $type) {
                $rows[] = $this->row(
                    $employee, $type, $month->addMonth(), $credit,
                    LedgerKind::MonthlyCredit, $period, "credit:{$employee->id}:{$type->value}:{$period}",
                );
            }
        }

        return $this->insert($rows);
    }

    /**
     * 30 for a whole month, otherwise the calendar days employed in it, at
     * most 30; 0 when the employee was not employed in it at all.
     */
    private function serviceDays(Employee $employee, CarbonImmutable $month): int
    {
        $first = $month->startOfMonth();
        $last = $month->endOfMonth()->startOfDay();
        $from = $employee->date_hired?->greaterThan($first) ? $employee->date_hired : $first;
        $to = $employee->date_separated?->lessThan($last) ? $employee->date_separated : $last;

        if ($from->greaterThan($to)) {
            return 0;
        }

        if ($from->equalTo($first) && $to->equalTo($last)) {
            return 30;
        }

        return min(30, (int) $from->diffInDays($to) + 1);
    }

    /**
     * Everyone who could earn: the date checks happen per month, against
     * each hire and separation date.
     *
     * @return Collection<int, Employee>
     */
    private function employees(): Collection
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return Employee::query()
            ->whereIn('employment_status', $earning)
            ->where('is_active', true)
            ->get(['id', 'date_hired', 'date_separated']);
    }

    /** @return array<string, mixed> */
    private function row(Employee $employee, LeaveType $type, CarbonImmutable $postedOn, string $days, LedgerKind $kind, string $period, ?string $key, ?string $remarks = null): array
    {
        $now = now();

        return [
            'employee_id' => $employee->id,
            'leave_type' => $type->value,
            'posted_on' => $postedOn->toDateString(),
            'days' => $days,
            'kind' => $kind->value,
            'period' => $period,
            'posting_key' => $key,
            'remarks' => $remarks,
            'created_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Without the model, so the activity log stays HR's, and ignoring rows
     * whose posting key is already taken.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(array $rows): int
    {
        return collect($rows)
            ->chunk(200)
            ->sum(fn ($chunk): int => LeaveLedgerEntry::query()->insertOrIgnore($chunk->values()->all()));
    }
}
