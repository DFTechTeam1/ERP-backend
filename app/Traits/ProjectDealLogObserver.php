<?php

namespace App\Traits;

use App\Enums\Production\Deal\DealLogAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Production\Models\ProjectDealLog;

trait ProjectDealLogObserver
{
    public static function bootProjectDealLogObserver()
    {
        static::created(function (Model $model) {
            $actor = User::select('id', 'email', 'employee_id')
                ->with([
                    'employee:id,name',
                ])
                ->find(Auth::id());

            $actorName = $actor->employee ? $actor->employee->name : 'Admin';
            $actorRole = $actor->roles?->first()?->name ?? 'no-role';

            $payload = [
                'action' => DealLogAction::Created,
                'project_deal_id' => $model->id,
                'description' => '',
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'meta' => [],
            ];

            ProjectDealLog::create($payload);
        });
    }
}
