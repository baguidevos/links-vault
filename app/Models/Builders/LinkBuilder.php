<?php

declare(strict_types=1);

namespace App\Models\Builders;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of \App\Models\Link
 *
 * @extends Builder<TModelClass>
 */
class LinkBuilder extends Builder
{
    /**
     * Add a basic where clause to the query.
     * Maps exact lookups on 'url' to the deterministic 'url_hash' column.
     *
     * @param  \Closure|string|array<mixed>|Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (
            is_string($column) &&
            in_array($column, ['url', 'links.url'], true) &&
            (func_num_args() === 2 || $operator === '=')
        ) {
            $plainUrl = func_num_args() === 2 ? $operator : $value;

            if (is_string($plainUrl)) {
                return parent::where('url_hash', hash('sha256', $plainUrl), null, $boolean);
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a "where in" clause to the query.
     * Maps lookups on 'url' to the deterministic 'url_hash' column.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (is_string($column) && in_array($column, ['url', 'links.url'], true)) {
            $hashedValues = collect($values)->map(fn ($url) => is_string($url) ? hash('sha256', $url) : $url)->all();

            return parent::whereIn('url_hash', $hashedValues, $boolean, $not);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }
}
