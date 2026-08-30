<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Folder $folder): bool
    {
        return $folder->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Folder $folder): bool
    {
        return $folder->canBeEditedBy($user);
    }

    public function delete(User $user, Folder $folder): bool
    {
        if ($folder->user_id === $user->id) {
            return true;
        }

        return $folder->team && $user->ownsTeam($folder->team);
    }
}
