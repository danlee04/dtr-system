<?php

namespace App\Filament\Resources\DtrUploads\Pages;

use App\Filament\Resources\DtrUploads\Actions\UploadDtrsAction;
use App\Filament\Resources\DtrUploads\DtrUploadResource;
use Filament\Resources\Pages\ListRecords;

class ListDtrUploads extends ListRecords
{
    protected static string $resource = DtrUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UploadDtrsAction::make(),
        ];
    }
}
