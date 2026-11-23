<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Who changed what, as the app already records it: read-only, for the admin
 * (ActivityPolicy). Rows the app posts without its models, such as the
 * monthly credits, are not recorded, so they are not here; the ledger has them.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $navigationLabel = 'Activity log';

    protected static bool $isGloballySearchable = false;

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
