<?php

namespace App\Filament\Widgets;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Models\LeaveApplication;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** How many applications are waiting for their signed form. */
class PendingLeaveApplications extends StatsOverviewWidget
{
    protected ?string $heading = 'Leave Applications';

    /** Everyone's leave, so only for whoever may open the list it links to. */
    public static function canView(): bool
    {
        return LeaveApplicationResource::canAccess();
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Pending leave applications', LeaveApplication::query()->where('status', LeaveApplicationStatus::Pending)->count())
                ->description('Waiting for the signed form')
                ->url(LeaveApplicationResource::getUrl('index', ['filters' => ['status' => ['value' => LeaveApplicationStatus::Pending->value]]])),
        ];
    }
}
