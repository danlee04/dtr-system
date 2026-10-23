<?php

namespace App\Filament\Resources\LeaveApplications\Actions;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApplications;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Cancels a rejected or withdrawn application, with the reason, and gives
 * back the days it drew.
 */
class CancelLeaveApplicationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancel';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (LeaveApplication $record): bool => $record->status !== LeaveApplicationStatus::Cancelled)
            ->authorize('cancel')
            ->modalHeading('Cancel this leave')
            ->modalDescription('The days it drew are given back.')
            ->schema([
                Textarea::make('cancel_reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (LeaveApplication $record, array $data, LeaveApplications $applications): void {
                $applications->cancel($record, $data['cancel_reason']);

                Notification::make()->success()->title('Leave cancelled; the days are back')->send();
            });
    }
}
