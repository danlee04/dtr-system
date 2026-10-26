<?php

namespace App\Filament\Employee\Pages;

use App\Enums\LeaveType;
use App\Enums\NavigationGroup;
use App\Services\Leave\LeaveLedger;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Every kind of leave, what it is for, and how much of it the signed-in
 * employee has left where it has a balance: the answer to "what leave can I
 * use for this?"
 */
class LeaveTypes extends Page
{
    protected string $view = 'filament.employee.pages.leave-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MyLeave;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Leave types';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCurrentEmployee() ?? false;
    }

    /** @return array{types: list<LeaveType>, balances: array<string, string>} */
    protected function getViewData(): array
    {
        return [
            'types' => LeaveType::cases(),
            'balances' => app(LeaveLedger::class)->balances(auth()->user()->employee),
        ];
    }
}
