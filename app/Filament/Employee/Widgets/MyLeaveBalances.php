<?php

namespace App\Filament\Employee\Widgets;

use App\Enums\LeaveType;
use App\Services\Leave\LeaveLedger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The signed-in employee's balances, VL first. */
class MyLeaveBalances extends StatsOverviewWidget
{
    protected ?string $heading = 'Balances';

    // A balance changes only when they or HR do something, never on its own.
    protected ?string $pollingInterval = null;

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $balances = app(LeaveLedger::class)->balances(auth()->user()->employee);

        return array_map(
            fn (LeaveType $type): Stat => Stat::make($type->getLabel(), $balances[$type->value])->description('days'),
            LeaveType::tracked(),
        );
    }
}
