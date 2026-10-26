<?php

namespace App\Policies;

use App\Models\ScheduleAssignment;
use App\Models\User;

/**
 * Staff assign schedules. The policy exists so that is stated rather than
 * assumed.
 */
class ScheduleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return $user->isStaff();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
