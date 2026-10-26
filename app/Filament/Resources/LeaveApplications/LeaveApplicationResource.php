<?php

namespace App\Filament\Resources\LeaveApplications;

use App\Enums\NavigationGroup;
use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\LeaveApplications\Pages\ViewLeaveApplication;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationInfolist;
use App\Filament\Resources\LeaveApplications\Tables\LeaveApplicationsTable;
use App\Models\LeaveApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Leave filed, by HR for now and by employees in L2b. There is no edit
 * page: a wrong application is cancelled, which gives its days back.
 */
class LeaveApplicationResource extends Resource
{
    protected static ?string $model = LeaveApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Leave applications';

    protected static ?string $modelLabel = 'leave application';

    /**
     * Everyone's leave, so staff only. The policy lets an employee view their
     * own, which they do from the employee panel.
     */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isStaff() ?? false) && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeaveApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveApplications::route('/'),
            'create' => CreateLeaveApplication::route('/create'),
            'view' => ViewLeaveApplication::route('/{record}'),
        ];
    }
}
