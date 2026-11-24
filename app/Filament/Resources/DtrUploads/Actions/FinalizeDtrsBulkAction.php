<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Enums\AttendanceStatus;
use App\Models\DtrUpload;
use App\Services\Attendance\ComputedDay;
use App\Services\Leave\DtrDeduction;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * HR marks the checked DTRs final at once (2026-11-24). Only a DTR with
 * nothing left to check goes through; the rest are named with why, so HR
 * opens them one by one.
 */
class FinalizeDtrsBulkAction extends BulkAction
{
    /** How many skipped DTRs the notification names before "and N more". */
    private const NAMED = 10;

    public static function getDefaultName(): ?string
    {
        return 'finalizeSelected';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Mark final')
            ->icon(Heroicon::OutlinedLockClosed)
            ->requiresConfirmation()
            ->modalHeading('Mark the selected DTRs final')
            ->modalDescription('Each one\'s late, undertime and absent days are deducted, as with Mark final on a single DTR. A DTR with a blank to fill, a day not computed or a day without a schedule is left open and named after.')
            ->modalSubmitActionLabel('Mark final')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, DtrDeduction $deduction): void {
                $done = 0;
                $skipped = [];

                foreach ($records as $record) {
                    $reason = match (true) {
                        $record->isFinal() => 'already final',
                        ! $record->isComputed() => 'not computed',
                        Gate::denies('finalize', $record) => 'not allowed',
                        default => self::notReady($record),
                    };

                    if ($reason === null) {
                        try {
                            $deduction->finalize($record, auth()->user());
                            $done++;

                            continue;
                        } catch (ValidationException $exception) {
                            $reason = collect($exception->errors())->flatten()->first();
                        }
                    }

                    $skipped[] = ($record->employee->full_name ?? $record->employee_name).": {$reason}";
                }

                $total = $records->count();

                $notification = Notification::make()
                    ->status($skipped === [] ? 'success' : 'warning')
                    // Not Str::plural(): it makes "DTRS" of "DTR".
                    ->title(($done === $total ? $done : "{$done} of {$total}").($total === 1 ? ' DTR' : ' DTRs').' marked final')
                    // Names come from the printed DTR, so each line is escaped; the body is read as HTML.
                    ->body($skipped === [] ? null : 'Left open:<br>'.collect($skipped)->take(self::NAMED)->map(e(...))->implode('<br>')
                        .(count($skipped) > self::NAMED ? '<br>and '.(count($skipped) - self::NAMED).' more' : ''));

                // Stays until closed when it names DTRs, so HR can read the list.
                if ($skipped !== []) {
                    $notification->persistent();
                }

                $notification->send();
            });
    }

    /** Why the DTR still needs HR's eye, or null when it is ready to be final. */
    private static function notReady(DtrUpload $upload): ?string
    {
        $days = $upload->computed()->days;
        $blanks = array_sum(array_map(fn (ComputedDay $day): int => count($day->missing), $days));
        $statuses = array_map(fn (ComputedDay $day): AttendanceStatus => $day->status, $days);

        return match (true) {
            $blanks > 0 => "{$blanks} ".Str::plural('blank', $blanks).' to fill',
            in_array(AttendanceStatus::NotComputed, $statuses, true) => 'a day not computed',
            in_array(AttendanceStatus::NoSchedule, $statuses, true) => 'a day without a schedule',
            default => null,
        };
    }
}
