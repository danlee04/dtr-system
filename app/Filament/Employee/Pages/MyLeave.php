<?php

namespace App\Filament\Employee\Pages;

use App\Filament\Employee\Widgets\MyLeaveBalances;
use App\Filament\Employee\Widgets\MyPendingApplications;
use Filament\Pages\Dashboard;

/** The employee panel's home: their balances and what is still pending. */
class MyLeave extends Dashboard
{
    protected static ?string $title = 'My leave';

    public function getWidgets(): array
    {
        return [
            MyLeaveBalances::class,
            MyPendingApplications::class,
        ];
    }
}
