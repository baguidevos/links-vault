<?php

namespace App\Concerns;

trait AddUserId
{
    protected static function bootAddUserId(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }
}
