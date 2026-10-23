<?php

namespace App\Console\Commands;

use App\Services\Leave\LeavePosting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs every hour from the scheduler. Posting is idempotent, so the hourly
 * run costs nothing when nothing is due and catches up after the PC was off.
 */
#[Signature('leave:post')]
#[Description('Post the leave credits that are due')]
class PostLeaveCredits extends Command
{
    public function handle(LeavePosting $posting): int
    {
        if (blank(config('leave.start_date'))) {
            $this->error('Set LEAVE_START_DATE in .env (the 1st of the month the system takes over).');

            return self::FAILURE;
        }

        $start = LeavePosting::configuredStart();

        if ($start === null) {
            $this->error('LEAVE_START_DATE must be the 1st of a month, written as YYYY-MM-DD; it is "'.config('leave.start_date').'".');

            return self::FAILURE;
        }

        $count = $posting->post($start, CarbonImmutable::today());

        $this->info("Posted {$count} ".str('row')->plural($count).'.');

        return self::SUCCESS;
    }
}
