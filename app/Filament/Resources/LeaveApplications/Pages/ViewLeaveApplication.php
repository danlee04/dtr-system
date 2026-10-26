<?php

namespace App\Filament\Resources\LeaveApplications\Pages;

use App\Filament\Resources\LeaveApplications\Actions\ApproveLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\CancelLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\Actions\PrintLeaveApplicationAction;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLeaveApplication extends ViewRecord
{
    protected static string $resource = LeaveApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrintLeaveApplicationAction::make(),
            ApproveLeaveApplicationAction::make(),
            CancelLeaveApplicationAction::make(),
        ];
    }
}
