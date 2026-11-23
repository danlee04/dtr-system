<?php

namespace App\Services\Leave;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Takes a DTR's late and undertime, as days by the CSC table, off leave when
 * HR marks it final: the part its absent days stand for off SL, then VL; the
 * rest off VL. Gives them back when HR reopens it (HR, 2026-11-19).
 *
 * Final waits for HR, not the upload: the total changes while HR fills blanks
 * from the signed DTR. SL never goes below zero, but VL takes the rest even
 * when it is short, so VL can go negative; HR counts what is short as without
 * pay. A DTR from before LEAVE_START_DATE deducts nothing: the opening balance
 * already holds those months. Reopen posts the opposite of what the DTR's rows
 * took from each balance, as cancelling a leave application does, so nothing
 * is ever deleted.
 */
final class DtrDeduction
{
    /** The days Final would take off SL and VL together now, "0.000" when it takes nothing. */
    public function due(DtrUpload $upload): string
    {
        if (! $this->deductible($upload)) {
            return '0.000';
        }

        return $upload->computed()->dayEquivalent;
    }

    /** Why Final takes nothing, or null when it takes something. */
    public function whyNothing(DtrUpload $upload): ?string
    {
        $start = LeavePosting::configuredStart();

        return match (true) {
            bccomp($this->due($upload), '0', 3) > 0 => null,
            $start === null => 'Nothing is deducted: the leave start date is not set.',
            $upload->period_start->lessThan($start) => "Nothing is deducted: this DTR is from before the leave credits start ({$start->format('M j, Y')}).",
            default => 'Nothing is deducted: no late, undertime or absence.',
        };
    }

    /** What the DTR's rows took and have not given back, from every balance, as a positive number of days. */
    public function deducted(DtrUpload $upload): string
    {
        return bcsub('0', number_format((float) $upload->ledgerEntries()->sum('days'), 3, '.', ''), 3);
    }

    /**
     * What the DTR's rows took and have not given back, per balance, as positive days, SL before VL.
     *
     * @return array<string, string> leave type code → days
     */
    public function deductedByType(DtrUpload $upload): array
    {
        return $this->netByType($upload)
            ->map(fn (string $net): string => bcsub('0', $net, 3))
            ->filter(fn (string $days): bool => bccomp($days, '0', 3) !== 0)
            ->sortKeys()
            ->all();
    }

    public function finalize(DtrUpload $upload, User $by): void
    {
        // Absent days read the SL balance, so the credits already due go in first.
        if ($this->deductible($upload) && bccomp($upload->computed()->absentCharge, '0', 3) > 0) {
            app(LeavePosting::class)->catchUp();
        }

        DB::transaction(function () use ($upload, $by): void {
            // A second click that waited on the lock finds it already final.
            $locked = DtrUpload::query()->whereKey($upload->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                throw ValidationException::withMessages(['dtr' => 'This DTR is already final.']);
            }

            if (! $locked->isComputed()) {
                throw ValidationException::withMessages(['dtr' => 'This DTR is not computed, so it cannot be marked final.']);
            }

            if ($this->deductible($locked)) {
                $this->post($locked, $by);
            }

            $locked->update(['finalized_at' => now(), 'finalized_by_user_id' => $by->id]);
        });

        $upload->refresh();
    }

    /** No reason is asked: HR reopens in one click (HR, 2026-11-20). */
    public function reopen(DtrUpload $upload, User $by): void
    {
        DB::transaction(function () use ($upload, $by): void {
            $locked = DtrUpload::query()->whereKey($upload->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isFinal()) {
                throw ValidationException::withMessages(['dtr' => 'This DTR is not final.']);
            }

            foreach ($this->netByType($locked) as $type => $net) {
                if (bccomp($net, '0', 3) !== 0) {
                    $this->row($locked, LeaveType::from($type), bcsub('0', $net, 3), LedgerKind::DtrReopened, DtrUploadInfolist::month($locked).' DTR reopened', $by);
                }
            }

            $locked->update(['finalized_at' => null, 'finalized_by_user_id' => null]);
        });

        $upload->refresh();
    }

    /** A computed DTR from the leave start on: one before it is in the opening balance. */
    private function deductible(DtrUpload $upload): bool
    {
        $start = LeavePosting::configuredStart();

        return $upload->isComputed() && $start !== null && ! $upload->period_start->lessThan($start);
    }

    /** The CSC days of late and undertime: the absent days' part off SL as far as it goes, then VL; the rest off VL. */
    private function post(DtrUpload $upload, User $by): void
    {
        $computed = $upload->computed();
        $month = DtrUploadInfolist::month($upload);
        $lateAndUndertime = self::lateAndUndertime($upload);

        if (bccomp($lateAndUndertime, '0', 3) > 0) {
            $this->row($upload, LeaveType::Vacation, bcsub('0', $lateAndUndertime, 3), LedgerKind::DtrDeduction, "{$month} DTR: ".DtrUploadInfolist::hours($computed->late + $computed->undertime - $computed->absentMinutes).' late and undertime', $by);
        }

        if (bccomp($computed->absentCharge, '0', 3) <= 0) {
            return;
        }

        // Filing leave locks the employee too, so SL is not drawn twice at once.
        $employee = Employee::withTrashed()->whereKey($upload->employee_id)->lockForUpdate()->firstOrFail();
        $sick = app(LeaveLedger::class)->balance($employee, LeaveType::Sick);
        $available = bccomp($sick, '0', 3) > 0 ? $sick : '0.000';
        $fromSick = bccomp($available, $computed->absentCharge, 3) < 0 ? $available : $computed->absentCharge;
        $remarks = "{$month} DTR: {$computed->absent} absent ".Str::plural('day', $computed->absent === 1.0 ? 1 : 2);

        if (bccomp($fromSick, '0', 3) > 0) {
            $this->row($upload, LeaveType::Sick, bcsub('0', $fromSick, 3), LedgerKind::DtrDeduction, $remarks, $by);
        }

        $fromVacation = bcsub($computed->absentCharge, $fromSick, 3);

        if (bccomp($fromVacation, '0', 3) > 0) {
            $this->row($upload, LeaveType::Vacation, bcsub('0', $fromVacation, 3), LedgerKind::DtrDeduction, $remarks, $by);
        }
    }

    /**
     * The days without pay its Final made, for payroll: how far its VL rows
     * took VL below zero (HR, 2026-11-20). Only the latest Final counts, so a
     * Reopen cancels it; a later credit that fills the negative does not.
     */
    public function withoutPay(DtrUpload $upload): string
    {
        if (! $upload->isFinal()) {
            return '0.000';
        }

        $lastReopen = $upload->ledgerEntries()->where('kind', LedgerKind::DtrReopened)->max('id') ?? 0;
        $rows = $upload->ledgerEntries()
            ->where('kind', LedgerKind::DtrDeduction)
            ->where('leave_type', LeaveType::Vacation)
            ->where('id', '>', $lastReopen)
            ->get(['id', 'days']);

        if ($rows->isEmpty()) {
            return '0.000';
        }

        // A later month's DTR is left out of the VL it found, or reopening this one after that month's Final would count those days twice.
        $laterDtrs = DtrUpload::query()
            ->where('employee_id', $upload->employee_id)
            ->whereDate('period_start', '>', $upload->period_start->toDateString())
            ->pluck('id');
        $before = number_format((float) LeaveLedgerEntry::query()
            ->where('employee_id', $upload->employee_id)
            ->where('leave_type', LeaveType::Vacation)
            ->where('id', '<', $rows->min('id'))
            ->where(fn ($query) => $query->whereNull('dtr_upload_id')->orWhereNotIn('dtr_upload_id', $laterDtrs))
            ->sum('days'), 3, '.', '');
        $after = bcadd($before, number_format((float) $rows->sum('days'), 3, '.', ''), 3);
        $owed = fn (string $balance): string => bccomp($balance, '0', 3) < 0 ? bcsub('0', $balance, 3) : '0.000';

        return bcsub($owed($after), $owed($before), 3);
    }

    /** The days off VL for late and undertime alone: the month's CSC days less the absent days' part, so the two add up to the total shown. */
    public static function lateAndUndertime(DtrUpload $upload): string
    {
        return bcsub($upload->computed()->dayEquivalent, $upload->computed()->absentCharge, 3);
    }

    /** @return Collection<string, string> leave type code → the signed sum of the DTR's rows */
    private function netByType(DtrUpload $upload): Collection
    {
        return $upload->ledgerEntries()
            ->selectRaw('leave_type, sum(days) as total')
            ->groupBy('leave_type')
            ->toBase()
            ->pluck('total', 'leave_type')
            ->map(fn (mixed $total): string => number_format((float) $total, 3, '.', ''));
    }

    private function row(DtrUpload $upload, LeaveType $type, string $days, LedgerKind $kind, string $remarks, User $by): void
    {
        $upload->ledgerEntries()->create([
            'employee_id' => $upload->employee_id,
            'leave_type' => $type,
            'posted_on' => today(),
            'days' => $days,
            'kind' => $kind,
            'period' => $upload->period_start->format('Y-m'),
            'remarks' => $remarks,
            'created_by_user_id' => $by->id,
        ]);
    }
}
