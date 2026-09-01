<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Link;
use App\Services\SemanticSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateLinkEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Link $link,
        public bool $force = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SemanticSearchService $service): void
    {
        try {
            $service->indexLink($this->link, $this->force);
        } catch (Throwable $e) {
            Log::warning("Erreur lors de l'indexation vectorielle du lien #{$this->link->id} : ".$e->getMessage());
        }
    }
}
