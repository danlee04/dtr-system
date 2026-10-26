<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

/**
 * Staff manage offices. The policy exists so that is stated rather than
 * assumed, and so the delete rule has a home.
 */
class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Office $office): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Office $office): bool
    {
        return $user->isStaff();
    }

    /**
     * Not while anyone belongs to it, deleted employees included: their rows
     * still point here. Deactivate the office instead.
     */
    public function delete(User $user, Office $office): bool
    {
        return $user->isStaff() && ! $office->employees()->withTrashed()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
