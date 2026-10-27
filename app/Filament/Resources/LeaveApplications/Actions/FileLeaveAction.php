<?php

namespace App\Filament\Resources\LeaveApplications\Actions;

use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApplications;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Files leave in a modal through LeaveApplications, which counts, charges and
 * deducts. On HR's list the form picks the employee; on an employee's own list,
 * or on an employee's Leave tab, the employee is fixed with employee().
 */
class FileLeaveAction extends Action
{
    protected ?Closure $employee = null;

    public static function getDefaultName(): ?string
    {
        return 'fileLeave';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('File leave')
            ->icon(Heroicon::OutlinedDocumentPlus)
            // Not authorize('create'): on a relation manager that would ask the ledger's policy.
            ->authorize(fn (): bool => Gate::allows('create', LeaveApplication::class))
            ->modalHeading('File leave')
            ->modalSubmitActionLabel('File')
            ->schema(fn (): array => $this->employee === null
                ? LeaveApplicationForm::components()
                : LeaveApplicationForm::fields(fn (): ?Employee => $this->evaluate($this->employee)))
            ->action(function (array $data): void {
                $employee = $this->employee === null
                    ? Employee::findOrFail($data['employee_id'])
                    : $this->evaluate($this->employee);

                try {
                    $application = app(LeaveApplications::class)->file($employee, $data);
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
                    ->title('Leave filed')
                    ->body("{$application->dates()}, {$application->days} days. Print the form for signing.")
                    ->actions([
                        Action::make('print')
                            ->label('Print form')
                            ->url(route('leave-applications.print', $application), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /** @param  Closure(): ?Employee  $employee */
    public function employee(Closure $employee): static
    {
        $this->employee = $employee;

        return $this;
    }
}
