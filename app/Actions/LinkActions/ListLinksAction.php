<?php

namespace App\Actions\LinkActions;

use App\Models\Link;
use Illuminate\Database\Eloquent\Collection;

class ListLinksAction
{
    public static function execute(array $filters = [], string $orderBy = 'created_at', string $orderDirection = 'desc'): Collection
    {
        $query = Link::query();

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (isset($filters['is_favorite'])) {
            $query->where('is_favorite', $filters['is_favorite']);
        }

        if (isset($filters['is_archived'])) {
            $query->where('is_archived', $filters['is_archived']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy($orderBy, $orderDirection)->get();
    }
}
