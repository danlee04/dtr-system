<?php

namespace App\Filament\Resources\Holidays;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Filament\Resources\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Holidays\Tables\HolidaysTable;
use App\Models\Holiday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    protected static ?int $navigationSort = 3;

    protected static ?string $pluralModelLabel = 'holidays & suspensions';

    protected static ?string $recordTitleAttribute = 'name';

    // Its records are edited in a modal on the list, so there is no page for a search result to open.
    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return HolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
        ];
    }
}
