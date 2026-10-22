<?php

namespace App\Policies;

use App\Models\RosterEntry;
use App\Models\User;

/**
 * Both roles keep the duty roster. The policy exists so that is stated rather
 * than assumed, and so the roster page has something to authorize against.
 */
class RosterEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RosterEntry $rosterEntry): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RosterEntry $rosterEntry): bool
    {
        return true;
    }

    public function delete(User $user, RosterEntry $rosterEntry): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
