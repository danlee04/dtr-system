<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

/**
 * Both roles keep the holiday list. The policy exists so that is stated rather
 * than assumed.
 */
class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return true;
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
