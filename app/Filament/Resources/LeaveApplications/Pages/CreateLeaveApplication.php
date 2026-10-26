<?php

namespace App\Filament\Resources\LeaveApplications\Pages;

use App\Filament\Concerns\FilesLeave;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Models\Employee;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeaveApplication extends CreateRecord
{
    use FilesLeave;

    protected static string $resource = LeaveApplicationResource::class;

    protected static ?string $title = 'File leave';

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->fileLeaveFor(Employee::findOrFail($data['employee_id']), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
