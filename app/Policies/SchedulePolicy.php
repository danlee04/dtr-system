<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Staff manage schedules. The policy exists so that is stated rather than
 * assumed, and so the delete rule has a home.
 */
class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->isStaff();
    }

    /**
     * Not while anyone is assigned it or rostered on it, past months included:
     * they still say what those days were. Deactivate the schedule instead.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->isStaff()
            && ! $schedule->assignments()->exists()
            && ! $schedule->rosterEntries()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
