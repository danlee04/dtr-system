<?php

namespace App\Policies;

use App\Models\DtrUpload;
use App\Models\User;

/**
 * Staff upload the IT Unit's DTRs and say whose they are. An employee does not
 * see the list: it holds everyone's times.
 */
class DtrUploadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
