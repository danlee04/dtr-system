<?php

namespace App\Policies;

use App\Models\LeaveLedgerEntry;
use App\Models\User;

/**
 * Both roles read balances and add rows. Nobody edits or deletes a row: a
 * mistake is corrected with another row, so the history stays whole.
 */
class LeaveLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveLedgerEntry $leaveLedgerEntry): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeaveLedgerEntry $leaveLedgerEntry): bool
    {
        return false;
    }

    public function delete(User $user, LeaveLedgerEntry $leaveLedgerEntry): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, LeaveLedgerEntry $leaveLedgerEntry): bool
    {
        return false;
    }

    public function forceDelete(User $user, LeaveLedgerEntry $leaveLedgerEntry): bool
    {
        return false;
    }
}
