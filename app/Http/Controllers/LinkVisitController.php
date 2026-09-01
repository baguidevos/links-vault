<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkVisitController extends Controller
{
    /**
     * Enregistre le clic / visite sur le lien et redirige immédiatement vers l'URL externe.
     */
    public function __invoke(Request $request, Link $link): RedirectResponse
    {
        $link->increment('visit_count');
        $link->update(['last_visited_at' => now()]);

        return redirect()->away($link->url);
    }
}
