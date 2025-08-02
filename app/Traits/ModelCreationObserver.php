<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait ModelCreationObserver
{
    public static function bootModelCreationObserver()
    {
        static::creating(function (Model $model) {
            $model->created_by = auth()->id();
        });

        static::updating(function (Model $model) {
            $model->updated_by = auth()->id();
        });
    }
}
