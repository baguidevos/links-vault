<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\LinkActions\GenerateAiSummaryAction;
use App\Models\Link;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLinkAiSummaryJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Link $link
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        GenerateAiSummaryAction::execute($this->link);
    }
}
