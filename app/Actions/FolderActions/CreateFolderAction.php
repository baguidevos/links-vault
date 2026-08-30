<?php

declare(strict_types=1);

namespace App\Actions\FolderActions;

use App\Models\Folder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

class CreateFolderAction
{
    public static function execute(array $data): Folder
    {
        $members = $data['members'] ?? [];
        unset($data['members']);

        if (empty($data['team_id'])) {
            if (class_exists(Filament::class) && Filament::getTenant()) {
                $data['team_id'] = Filament::getTenant()->id;
            } elseif (auth()->check() && auth()->user()->current_team_id) {
                $data['team_id'] = auth()->user()->current_team_id;
            }
        }

        if (empty($data['user_id']) && auth()->check()) {
            $data['user_id'] = auth()->id();
        }

        $folder = Folder::create([
            ...$data,
            'slug' => ! empty($data['slug']) ? $data['slug'] : Str::slug($data['name']),
        ]);

        if (! empty($members) && $folder->visibility->value === 'restricted') {
            $syncData = [];
            foreach ($members as $member) {
                if (! empty($member['user_id'])) {
                    $syncData[$member['user_id']] = ['role' => $member['role'] ?? 'viewer'];
                }
            }
            $folder->members()->sync($syncData);
        }

        return $folder;
    }
}
