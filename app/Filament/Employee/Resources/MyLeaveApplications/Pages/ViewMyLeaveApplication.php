<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Pages;

use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMyLeaveApplication extends ViewRecord
{
    protected static string $resource = MyLeaveApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrintLeaveApplicationAction::make(),
            CancelLeaveApplicationAction::make(),
        ];
    }
}
