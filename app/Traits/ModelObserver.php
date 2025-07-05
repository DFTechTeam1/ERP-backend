<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

trait ModelObserver
{
    public static function bootModelObserver()
    {
        static::creating(function (Model $model) {
            $model->uid = Uuid::uuid4();
        });

        // static::retrieved(function (Model $model) {
        //     if (isset($model['name'])) {
        //         $model->name = ucwords($model->name);
        //     }
        // });
    }
}
