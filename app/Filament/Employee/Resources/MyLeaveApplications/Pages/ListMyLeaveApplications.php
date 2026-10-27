<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Pages;

use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Actions\FileLeaveAction;
use App\Models\Employee;
use Filament\Resources\Pages\ListRecords;

class ListMyLeaveApplications extends ListRecords
{
    protected static string $resource = MyLeaveApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // For the signed-in employee only: the form has no employee field to change.
            FileLeaveAction::make()
                ->employee(fn (): ?Employee => auth()->user()->employee),
        ];
    }
}
