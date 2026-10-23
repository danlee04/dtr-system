<?php

namespace App\Policies;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\User;

/**
 * Both roles file, approve and cancel. An application is never edited or
 * deleted: a wrong one is cancelled, which gives its days back.
 */
class LeaveApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveApplication $leaveApplication): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === LeaveApplicationStatus::Pending;
    }

    public function cancel(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status !== LeaveApplicationStatus::Cancelled;
    }

    public function update(User $user, LeaveApplication $leaveApplication): bool
    {
        return false;
    }

    public function delete(User $user, LeaveApplication $leaveApplication): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, LeaveApplication $leaveApplication): bool
    {
        return false;
    }

    public function forceDelete(User $user, LeaveApplication $leaveApplication): bool
    {
        return false;
    }
}
