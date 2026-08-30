<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FolderPolicy
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
        // Seul le créateur ou le propriétaire de l'équipe peut supprimer le dossier
        if ($folder->user_id === $user->id) {
            return true;
        }

        return method_exists($user, 'isCurrentTeamOwner') && $user->isCurrentTeamOwner();
    }
}
