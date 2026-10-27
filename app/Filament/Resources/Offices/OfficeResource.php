<?php

namespace App\Filament\Resources\Offices;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Filament\Resources\Offices\Schemas\OfficeForm;
use App\Filament\Resources\Offices\Tables\OfficesTable;
use App\Models\Office;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Personnel;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    // Its records are edited in a modal on the list, so there is no page for a search result to open.
    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return OfficeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffices::route('/'),
        ];
    }
}
