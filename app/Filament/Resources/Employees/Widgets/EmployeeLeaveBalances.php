<?php

namespace App\Filament\Resources\Employees\Widgets;

use App\Enums\LeaveType;
use App\Models\Employee;
use App\Services\Leave\LeaveLedger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** The employee page's balances, above its tabs, the way the dashboard shows one's own. */
class EmployeeLeaveBalances extends StatsOverviewWidget
{
    /** @var Employee|null */
    public ?Model $record = null;

    protected ?string $heading = 'Leave Balances';

    // A balance changes only when someone files, cancels or adjusts.
    protected ?string $pollingInterval = null;

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $balances = app(LeaveLedger::class)->balances($this->record);

        return array_map(
            fn (LeaveType $type): Stat => Stat::make($type->getLabel(), $balances[$type->value])->description('days'),
            LeaveType::tracked(),
        );
    }
}
