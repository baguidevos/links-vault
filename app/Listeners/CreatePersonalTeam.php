<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\TeamActions\CreateTeam;

class CreatePersonalTeam
{
    public function __construct(private readonly CreateTeam $action) {}

    public function handle(object $event): void
    {
        if (! config('filateams.create_personal_team_on_registration', true)) {
            return;
        }

        $user = method_exists($event, 'getUser') ? $event->getUser() : ($event->user ?? null);

        if (! $user) {
            return;
        }

        $teamName = __('filateams::filateams.personal_team_name', ['name' => $user->name]);
        if (str_starts_with($teamName, 'filateams::')) {
            $teamName = "Espace de {$user->name}";
        }

        $this->action->handle($user, [
            'name' => $teamName,
            'is_personal' => true,
        ]);
    }
}
