<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Both roles manage employees. Nobody removes one for good: an employee's
 * punches and computed days point at that row, so a delete is always soft and
 * always reversible.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Employee $employee): bool
    {
        return true;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return true;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return true;
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
