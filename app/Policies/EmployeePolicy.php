<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;

/**
 * Staff manage employees. Nobody removes one for good: an employee's
 * punches and computed days point at that row, so a delete is always soft and
 * always reversible.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isStaff();
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->isStaff();
    }

    /**
     * Staff make employees' own logins and reset their passwords. A staff
     * account linked to an employee is not one of those: an admin keeps it on
     * the Users screen.
     */
    public function manageLogin(User $user, ?Employee $employee = null): bool
    {
        return $user->isStaff()
            && ($employee?->user === null || $employee->user->role === UserRole::Employee);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
