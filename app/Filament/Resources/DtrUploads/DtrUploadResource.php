<?php

namespace App\Filament\Resources\DtrUploads;

use App\Enums\NavigationGroup;
use App\Filament\Resources\DtrUploads\Pages\ListDtrUploads;
use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Filament\Resources\DtrUploads\Tables\DtrUploadsTable;
use App\Models\DtrUpload;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The DTRs the IT Unit prints, uploaded as PDFs: who each belongs to and what
 * each day's row shows. Uploading and viewing open in modals. There is no
 * editing: a DTR is as IT printed it, and a corrected one is uploaded again.
 */
class DtrUploadResource extends Resource
{
    protected static ?string $model = DtrUpload::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::TimeAndAttendance;

    // First in its group: it is where the month's attendance starts.
    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'DTR upload';

    protected static ?string $pluralModelLabel = 'DTR uploads';

    protected static ?string $recordTitleAttribute = 'employee_name';

    // Its records open in a modal on the list, so there is no page for a search result to open.
    protected static bool $isGloballySearchable = false;

    public static function infolist(Schema $schema): Schema
    {
        return DtrUploadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DtrUploadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDtrUploads::route('/'),
        ];
    }
}
