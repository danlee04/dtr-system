<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications\Pages;

use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMyLeaveApplications extends ListRecords
{
    protected static string $resource = MyLeaveApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('File leave'),
        ];
    }
}
