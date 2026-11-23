<?php

namespace App\Policies;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\User;

/**
 * Staff file, change, approve, disapprove, cancel and print any application.
 * An employee files their own, sees and prints their own, and changes or
 * cancels their own while it is pending (HR, 2026-11-23). An application is
 * changed only while pending and never deleted: a wrong approved one is
 * cancelled, which gives its days back.
 */
class LeaveApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->employee_id !== null;
    }

    public function view(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->isStaff() || $this->owns($user, $leaveApplication);
    }

    public function create(User $user): bool
    {
        return $user->isStaff() || $user->employee_id !== null;
    }

    public function approve(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->isStaff() && $leaveApplication->status === LeaveApplicationStatus::Pending;
    }

    public function disapprove(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->isStaff() && $leaveApplication->status === LeaveApplicationStatus::Pending;
    }

    public function cancel(User $user, LeaveApplication $leaveApplication): bool
    {
        if (! $leaveApplication->status->stands()) {
            return false;
        }

        return $user->isStaff()
            || ($this->owns($user, $leaveApplication) && $leaveApplication->status === LeaveApplicationStatus::Pending);
    }

    public function print(User $user, LeaveApplication $leaveApplication): bool
    {
        return $this->view($user, $leaveApplication);
    }

    public function update(User $user, LeaveApplication $leaveApplication): bool
    {
        return $leaveApplication->status === LeaveApplicationStatus::Pending
            && ($user->isStaff() || $this->owns($user, $leaveApplication));
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

    private function owns(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->employee_id !== null && $leaveApplication->employee_id === $user->employee_id;
    }
}
