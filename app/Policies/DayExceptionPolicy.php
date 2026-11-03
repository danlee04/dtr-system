<?php

namespace App\Policies;

use App\Models\DayException;
use App\Models\User;

/**
 * Staff enter OBs and travel orders from the signed slips. An employee does
 * not see the list: it holds everyone's.
 */
class DayExceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, DayException $dayException): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, DayException $dayException): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, DayException $dayException): bool
    {
        return $user->isStaff();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
