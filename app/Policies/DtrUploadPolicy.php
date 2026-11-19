<?php

namespace App\Policies;

use App\Models\DtrUpload;
use App\Models\User;

/**
 * Staff upload the IT Unit's DTRs and say whose they are. An employee does not
 * see the list: it holds everyone's times. A DTR marked final is locked, so
 * the VL it deducted keeps matching it, until HR reopens it.
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
        return $user->isStaff() && ! $dtrUpload->isFinal();
    }

    public function delete(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff() && ! $dtrUpload->isFinal();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Only a computed DTR has late and undertime to deduct. */
    public function finalize(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff() && ! $dtrUpload->isFinal() && $dtrUpload->isComputed();
    }

    public function reopen(User $user, DtrUpload $dtrUpload): bool
    {
        return $user->isStaff() && $dtrUpload->isFinal();
    }
}
