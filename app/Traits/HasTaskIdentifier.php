<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasTaskIdentifier
{
    public static function bootHasTaskIdentifier()
    {
        static::creating(function (Model $model) {
            $model->created_by = auth()->id();
            $model->task_identifier_id = generateRandomPassword(4);
        });

        static::updating(function (Model $model) {
            $model->updated_by = auth()->id();
        });
    }
}
