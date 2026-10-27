<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\Actions\ImportEmployeesAction;
use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportEmployeesAction::make(),
            // Wider than Filament's default, so four fields fit across with room; the Edit modals match.
            CreateAction::make()
                ->modalWidth(Width::SixExtraLarge),
        ];
    }
}
