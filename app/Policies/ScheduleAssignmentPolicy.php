<?php

namespace App\Policies;

use App\Models\ScheduleAssignment;
use App\Models\User;

/**
 * Both roles assign schedules. The policy exists so that is stated rather than
 * assumed.
 */
class ScheduleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return true;
    }

    public function delete(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
