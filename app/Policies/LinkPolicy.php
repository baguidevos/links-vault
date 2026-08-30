<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Link;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LinkPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Link $link): bool
    {
        if ($link->user_id === $user->id) {
            return true;
        }

        if (method_exists($user, 'isCurrentTeamOwner') && $user->isCurrentTeamOwner()) {
            return true;
        }

        if ($link->folder && $link->folder->isAccessibleBy($user)) {
            return true;
        }

        return $link->shares()
            ->where('recipient_user_id', $user->id)
            ->valid()
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Link $link): bool
    {
        if ($link->user_id === $user->id) {
            return true;
        }

        if (method_exists($user, 'isCurrentTeamOwner') && $user->isCurrentTeamOwner()) {
            return true;
        }

        if ($link->folder && $link->folder->canBeEditedBy($user)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Link $link): bool
    {
        if ($link->user_id === $user->id) {
            return true;
        }

        if (method_exists($user, 'isCurrentTeamOwner') && $user->isCurrentTeamOwner()) {
            return true;
        }

        return false;
    }
}
