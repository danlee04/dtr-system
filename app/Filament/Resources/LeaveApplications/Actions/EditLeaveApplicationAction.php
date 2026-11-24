<?php

namespace App\Filament\Resources\LeaveApplications\Actions;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApplications;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Changes a pending application in the filing form, for HR or the employee
 * whose it is (HR, 2026-11-23): it is charged again in place, so a wrong date
 * or type needs no cancelling and filing again. The employee stays.
 */
class EditLeaveApplicationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'edit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (LeaveApplication $record): bool => $record->status === LeaveApplicationStatus::Pending)
            ->authorize('update')
            ->modalHeading('Change Leave')
            ->modalDescription('Print the form again after the change.')
            ->modalSubmitActionLabel('Save')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->fillForm(fn (LeaveApplication $record): array => [
                'leave_type' => $record->leave_type->value,
                'date_from' => $record->date_from->toDateString(),
                'date_to' => $record->date_to->toDateString(),
                'coverage' => $record->coverage->value,
                'detail' => $record->detail?->value,
                'detail_text' => $record->detail_text,
                'division_head' => $record->division_head,
                'commutation_requested' => $record->commutation_requested,
            ])
            // Three across, like the filing form.
            ->schema(fn (LeaveApplication $record): array => [
                Grid::make(3)->schema(LeaveApplicationForm::fields(fn () => $record->employee, changing: $record)),
            ])
            ->action(function (LeaveApplication $record, array $data, LeaveApplications $applications): void {
                try {
                    $applications->update($record, $data);
                } catch (ValidationException $exception) {
                    // The service names fields; the modal's fields live under its own state path.
                    throw ValidationException::withMessages(
                        collect($exception->errors())
                            ->mapWithKeys(fn (array $messages, string $key): array => ["mountedActions.{$this->getNestingIndex()}.data.{$key}" => $messages])
                            ->all(),
                    );
                }

                Notification::make()
                    ->success()
                    ->title('Leave changed')
                    ->body("{$record->dates()}, {$record->days} days. Print the form again.")
                    ->actions([
                        Action::make('print')
                            ->label('Print form')
                            ->url(route('leave-applications.print', $record), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }
}
