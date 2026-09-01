<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Link;
use App\Models\Team;
use App\Services\LinkHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLinkHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  Link|null  $link  Vérifier un lien spécifique
     * @param  array<int>|null  $linkIds  Vérifier une liste d'identifiants de liens
     * @param  Team|null  $team  Vérifier tous les liens d'une équipe
     * @param  bool  $force  Forcer la réévaluation même si déjà vérifié
     */
    public function __construct(
        public ?Link $link = null,
        public ?array $linkIds = null,
        public ?Team $team = null,
        public bool $force = false,
    ) {}

    public function handle(LinkHealthService $healthService): void
    {
        if ($this->link) {
            $healthService->checkLink($this->link);

            return;
        }

        if (! empty($this->linkIds)) {
            $links = Link::whereIn('id', $this->linkIds)->get();
            $healthService->checkCollection($links);

            return;
        }

        if ($this->team) {
            $healthService->checkTeamLinks($this->team, force: $this->force);
        }
    }
}
