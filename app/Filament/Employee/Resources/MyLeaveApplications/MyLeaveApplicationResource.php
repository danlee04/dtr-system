<?php

namespace App\Filament\Employee\Resources\MyLeaveApplications;

use App\Enums\NavigationGroup;
use App\Filament\Employee\Resources\MyLeaveApplications\Pages\CreateMyLeaveApplication;
use App\Filament\Employee\Resources\MyLeaveApplications\Pages\ListMyLeaveApplications;
use App\Filament\Employee\Resources\MyLeaveApplications\Pages\ViewMyLeaveApplication;
use App\Filament\Employee\Resources\MyLeaveApplications\Tables\MyLeaveApplicationsTable;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationInfolist;
use App\Models\Employee;
use App\Models\LeaveApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The signed-in employee's own leave: file, view, print and cancel while
 * pending. HR approves, on its Leave applications screen. There is no edit
 * page: a wrong application is cancelled, which gives its days back.
 */
class MyLeaveApplicationResource extends Resource
{
    protected static ?string $model = LeaveApplication::class;

    protected static ?string $slug = 'applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MyLeave;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'leave application';

    /** Only for an account linked to an employee who still works here. */
    public static function canAccess(): bool
    {
        return (auth()->user()?->hasCurrentEmployee() ?? false) && parent::canAccess();
    }

    /** Every page and action reads through this, so another employee's leave is never found. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('employee_id', auth()->user()->employee_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(LeaveApplicationForm::fields(fn (): ?Employee => auth()->user()->employee));
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeaveApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MyLeaveApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyLeaveApplications::route('/'),
            'create' => CreateMyLeaveApplication::route('/create'),
            'view' => ViewMyLeaveApplication::route('/{record}'),
        ];
    }
}
