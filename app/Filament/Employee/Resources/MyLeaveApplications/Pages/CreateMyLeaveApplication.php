<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Pages;

use App\Filament\Concerns\FilesLeave;
use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Files for the signed-in employee only: the form has no employee field to change. */
class CreateMyLeaveApplication extends CreateRecord
{
    use FilesLeave;

    protected static string $resource = MyLeaveApplicationResource::class;

    protected static ?string $title = 'File leave';

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->fileLeaveFor(auth()->user()->employee, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
