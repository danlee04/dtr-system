<?php

namespace App\Filament\Resources\LeaveApplications\Actions;

use App\Models\LeaveApplication;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/** Opens the filled HRM-07 form in a new tab, ready to print and sign. */
class PrintLeaveApplicationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'print';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Print form')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->authorize('print')
            ->url(fn (LeaveApplication $record): string => route('leave-applications.print', $record))
            ->openUrlInNewTab();
    }
}
