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
 * HR does not approve a pending application: the days it drew are given back
 * and the reason is printed in 7.D of the form (HR, 2026-11-23). Withdrawn
 * leave is cancelled instead.
 */
class DisapproveLeaveApplicationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'disapprove';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Disapprove')
            ->icon(Heroicon::OutlinedHandThumbDown)
            ->color('danger')
            ->visible(fn (LeaveApplication $record): bool => $record->status === LeaveApplicationStatus::Pending)
            ->authorize('disapprove')
            ->modalHeading('Disapprove this leave')
            ->modalDescription('The days it drew are given back. The reason is printed in 7.D of the form.')
            ->schema([
                Textarea::make('disapproval_reason')
                    ->label('Disapproved due to')
                    ->required()
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Disapprove')
            ->action(function (LeaveApplication $record, array $data, LeaveApplications $applications): void {
                $applications->disapprove($record, $data['disapproval_reason']);

                Notification::make()->success()->title('Leave disapproved; the days are back')->send();
            });
    }
}
