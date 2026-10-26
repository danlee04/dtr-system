<?php

namespace App\Policies;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\User;

/**
 * Staff file, approve, cancel and print any application. An employee files
 * their own, sees and prints their own, and cancels their own while it is
 * pending. An application is never edited or deleted: a wrong one is
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

    public function cancel(User $user, LeaveApplication $leaveApplication): bool
    {
        if ($leaveApplication->status === LeaveApplicationStatus::Cancelled) {
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

    private function owns(User $user, LeaveApplication $leaveApplication): bool
    {
        return $user->employee_id !== null && $leaveApplication->employee_id === $user->employee_id;
    }
}
