<?php

namespace App\Filament\Resources\DayExceptions\Pages;

use App\Filament\Resources\DayExceptions\DayExceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDayExceptions extends ListRecords
{
    protected static string $resource = DayExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by_user_id' => auth()->id()]),
        ];
    }
}
