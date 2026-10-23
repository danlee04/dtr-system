<?php

namespace App\Filament\Resources\LeaveApplications\Actions;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApplications;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Records that the signed form came back approved. The days were deducted
 * when the leave was filed, so nothing else changes.
 */
class ApproveLeaveApplicationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (LeaveApplication $record): bool => $record->status === LeaveApplicationStatus::Pending)
            ->authorize('approve')
            ->requiresConfirmation()
            ->modalDescription('Approve once the signed form is back.')
            ->action(function (LeaveApplication $record, LeaveApplications $applications): void {
                $applications->approve($record);

                Notification::make()->success()->title('Leave approved')->send();
            });
    }
}
