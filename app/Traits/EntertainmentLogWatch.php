<?php

namespace App\Traits;

use App\Enums\Entertainment\LogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin Model
 */
trait EntertainmentLogWatch
{
    public static function checkClass(Model $model, mixed $targetModel): bool
    {
        return get_class($model) === $targetModel;
    }

    public static function bootEntertainmentLogWatch(): void
    {
        static::created(function (Model $model) {
            if (self::checkClass($model, \Modules\Production\Models\EntertainmentTask::class)) {
                $employee = app(\Modules\Hrd\Repository\EmployeeRepository::class)->show(uid: '', select: 'id,nickname', where: "user_id = " . Auth::id());

                app(\Modules\Production\Services\EntertainmentLogService::class)->record(
                    projectId: $model->project_id,
                    type: LogType::createSong,
                    replace: [$employee->nickname]
                );
            }
        });
    }
}
