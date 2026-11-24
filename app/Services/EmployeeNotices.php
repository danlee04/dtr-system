<?php

namespace App\Services;

use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrUpload;
use App\Models\LeaveApplication;
use App\Models\User;
use Filament\Notifications\Notification;

/**
 * What an employee is told in the app, under the bell in their sidebar
 * (2026-11-24): HR approved, disapproved or cancelled their leave, or marked
 * their DTR final or reopened it. Sent to every account linked to the
 * employee; one with no account is told nothing.
 */
final class EmployeeNotices
{
    private const GIVEN_BACK = 'What it took from your balance was given back.';

    public static function leaveApproved(LeaveApplication $application): void
    {
        self::send($application->employee_id, Notification::make()
            ->success()
            ->title('Leave approved')
            ->body("Your {$application->leave_type->getLabel()}, {$application->dates()}, was approved."));
    }

    public static function leaveDisapproved(LeaveApplication $application): void
    {
        self::send($application->employee_id, Notification::make()
            ->danger()
            ->title('Leave disapproved')
            ->body("Your {$application->leave_type->getLabel()}, {$application->dates()}, was disapproved: ".self::reason($application->disapproval_reason).' '.self::GIVEN_BACK));
    }

    public static function leaveCancelled(LeaveApplication $application): void
    {
        // An employee who cancelled their own leave already knows.
        if (auth()->user()?->employee_id === $application->employee_id) {
            return;
        }

        self::send($application->employee_id, Notification::make()
            ->warning()
            ->title('Leave cancelled')
            ->body("HR cancelled your {$application->leave_type->getLabel()}, {$application->dates()}: ".self::reason($application->cancel_reason).' '.self::GIVEN_BACK));
    }

    /** @param  array<string, string>  $deducted  leave type code => days, as DtrDeduction::deductedByType() gives them */
    public static function dtrFinal(DtrUpload $upload, array $deducted): void
    {
        $deductions = DtrUploadInfolist::deductions($deducted);

        self::send($upload->employee_id, Notification::make()
            ->info()
            ->title(DtrUploadInfolist::month($upload).' DTR final')
            ->body($deductions === null ? 'Nothing was deducted.' : "Deducted: {$deductions}."));
    }

    public static function dtrReopened(DtrUpload $upload): void
    {
        self::send($upload->employee_id, Notification::make()
            ->info()
            ->title(DtrUploadInfolist::month($upload).' DTR reopened')
            ->body('HR reopened it to check it again. What it took was given back until it is marked final again.'));
    }

    /** HR's words, escaped: the bell reads the body as HTML. */
    private static function reason(?string $reason): string
    {
        return e(rtrim((string) $reason, '.')).'.';
    }

    private static function send(?int $employeeId, Notification $notification): void
    {
        if ($employeeId === null) {
            return;
        }

        // Sent now, not queued: no queue worker runs on this server, so a queued notice would never arrive.
        User::query()->where('employee_id', $employeeId)->each(fn (User $user) => $user->notifyNow($notification->toDatabase()));
    }
}
