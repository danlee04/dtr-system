<?php

namespace App\Services\Attendance;

use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves what DtrPdfReader read, and says whose DTR it is.
 *
 * The form prints only a name, so the upload goes to the employee with that
 * name when exactly one has it. A name that fits nobody, or several people,
 * is saved all the same and waits for HR to choose: guessing would put one
 * person's times on another's record.
 *
 * An employee has one DTR for a date. A DTR for someone who already has one
 * for any of its dates is skipped, neither doubled nor put in place of the
 * one there (HR, 2026-11-18): to load IT's reprint, HR deletes the old upload
 * first. Choosing the employee for an upload still replaces an older one.
 */
class DtrImporter
{
    /** @var array<string, list<int>>|null each name, as nameKey() reads it, to the employees who have it */
    private ?array $employeesByName = null;

    /** The saved upload, or null when the person already has a DTR for its dates. */
    public function import(PrintedDtr $dtr, string $fileName, ?User $uploadedBy): ?DtrUpload
    {
        $employeeId = $this->employeeNamed($dtr->employeeName);

        if ($this->alreadyUploaded($dtr, $employeeId)) {
            return null;
        }

        return DB::transaction(function () use ($dtr, $fileName, $uploadedBy, $employeeId): DtrUpload {
            $upload = DtrUpload::create([
                'employee_id' => $employeeId,
                'employee_name' => $dtr->employeeName,
                'period_start' => $dtr->periodStart,
                'period_end' => $dtr->periodEnd,
                'file_name' => $fileName,
                'uploaded_by_user_id' => $uploadedBy?->id,
            ]);

            foreach ($dtr->days as $date => $entries) {
                $upload->days()->create(['date' => $date, ...$entries]);
            }

            return $upload;
        });
    }

    /**
     * Whether an upload for any of the DTR's dates is already there for the
     * employee it names, or for the same printed name. A name two employees
     * share tells nobody apart, so it is not taken for an upload already there.
     *
     * shortcut: two people saving one month at the same moment can both pass; lock the employee's row if HR ever uploads from several desks at once.
     */
    private function alreadyUploaded(PrintedDtr $dtr, ?int $employeeId): bool
    {
        $nameKey = self::nameKey($dtr->employeeName);
        $nameIsShared = count($this->employeesByName[$nameKey] ?? []) > 1;

        return DtrUpload::query()
            ->whereDate('period_start', '<=', $dtr->periodEnd)
            ->whereDate('period_end', '>=', $dtr->periodStart)
            ->get(['employee_id', 'employee_name'])
            ->contains(fn (DtrUpload $upload): bool => ($employeeId !== null && $upload->employee_id === $employeeId)
                || (! $nameIsShared && self::nameKey($upload->employee_name) === $nameKey));
    }

    /** HR says whose DTR an upload is. */
    public function assign(DtrUpload $upload, Employee $employee): void
    {
        DB::transaction(function () use ($upload, $employee): void {
            $upload->update(['employee_id' => $employee->id]);
            $this->replaceOlder($upload);
        });
    }

    private function replaceOlder(DtrUpload $upload): void
    {
        if ($upload->employee_id === null) {
            return;
        }

        // One by one, so each removal reaches the activity log.
        // shortcut: two uploads of one employee's month saved at the same moment can both survive; lock the employee's row if HR ever uploads from several desks at once.
        DtrUpload::query()
            ->where('employee_id', $upload->employee_id)
            ->whereKeyNot($upload->getKey())
            ->whereDate('period_start', '<=', $upload->period_end)
            ->whereDate('period_end', '>=', $upload->period_start)
            ->get()
            ->each->delete();
    }

    private function employeeNamed(string $name): ?int
    {
        $this->employeesByName ??= Employee::query()
            ->get(['id', 'last_name', 'first_name', 'middle_name', 'suffix'])
            ->groupBy(fn (Employee $employee): string => self::nameKey($employee->full_name))
            ->map(fn ($employees): array => $employees->modelKeys())
            ->all();

        $ids = $this->employeesByName[self::nameKey($name)] ?? [];

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * "PEÑA JR., JUAN S." and "Peña, Juan S. Jr." are the same name: the
     * words of the name, in capitals, without accents or punctuation, in any
     * order. The list's full name already ends at the middle initial, as the
     * form prints it.
     */
    private static function nameKey(string $name): string
    {
        $words = preg_split('/[^A-Z0-9]+/', Str::upper(Str::ascii($name)), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        sort($words);

        return implode(' ', $words);
    }
}
