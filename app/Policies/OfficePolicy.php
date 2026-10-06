<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

/**
 * Both roles manage offices. The policy exists so that is stated rather than
 * assumed, and so the delete rule has a home.
 */
class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Office $office): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Office $office): bool
    {
        return true;
    }

    /**
     * Not while anyone belongs to it, deleted employees included: their rows
     * still point here. Deactivate the office instead.
     */
    public function delete(User $user, Office $office): bool
    {
        return ! $office->employees()->withTrashed()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
