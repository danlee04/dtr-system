<?php

namespace App\Filament\Resources\DayExceptions;

use App\Enums\NavigationGroup;
use App\Filament\Resources\DayExceptions\Pages\ListDayExceptions;
use App\Filament\Resources\DayExceptions\Schemas\DayExceptionForm;
use App\Filament\Resources\DayExceptions\Tables\DayExceptionsTable;
use App\Models\DayException;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Official business and travel orders, entered by HR from the signed slips,
 * so the DTR does not count those days late, under or absent.
 */
class DayExceptionResource extends Resource
{
    protected static ?string $model = DayException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    protected static ?int $navigationSort = 4;

    // "&", not "or": Filament capitalises every word of a heading, "Create OB, TO, Leave & Gate Pass".
    protected static ?string $modelLabel = 'OB, TO, leave & gate pass';

    protected static ?string $pluralModelLabel = 'OB, TO, leave & gate passes';

    // Short enough for the sidebar; a travel order is filed with the OBs.
    protected static ?string $navigationLabel = 'OB, Leave & Gate Pass';

    // Its records are edited in a modal on the list, so there is no page for a search result to open.
    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return DayExceptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DayExceptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDayExceptions::route('/'),
        ];
    }
}
