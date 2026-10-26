<?php

namespace App\Filament\Concerns;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApplications;
use Illuminate\Validation\ValidationException;

/**
 * Files through LeaveApplications, which counts, charges and deducts. Its
 * refusals are keyed by field name, so they are moved under "data." to show
 * on the field at fault. HR's create page and the employee's share it.
 */
trait FilesLeave
{
    /** @param  array<string, mixed>  $data */
    protected function fileLeaveFor(Employee $employee, array $data): LeaveApplication
    {
        try {
            return app(LeaveApplications::class)->file($employee, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }
}
