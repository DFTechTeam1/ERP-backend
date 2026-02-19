<?php

namespace App\Actions;

use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class DefineDetailProjectPermission
{
    use AsAction;

    public function handle()
    {
        $user = User::where('id', Auth::user()->id)->first();

        return [
            'list_member' => $user->hasPermissionTo('list_member'),
            'list_entertainment_member' => $user->hasPermissionTo('list_entertainment_member'),
            'add_team_member' => $user->hasPermissionTo('add_team_member'),
            'add_references' => $user->hasPermissionTo('add_references') && !$this->isRegularEntertainmentUser($user),
            'list_request_song' => $user->hasPermissionTo('list_request_song'),
            'create_request_song' => $user->hasPermissionTo('create_request_song'),
            'distribute_request_song' => $user->hasPermissionTo('distribute_request_song'),
            'add_showreels' => $user->hasPermissionTo('add_showreels'),
            'list_task' => $user->hasPermissionTo('list_task'),
            'move_task_to_progress' => false,
            'move_task_to_review_client' => false,
            'move_task_to_review_pm' => false,
            'move_task_to_revise' => false,
            'move_task_to_completed' => false,
            'move_task' => false,
            'add_task' => $user->hasPermissionTo('add_task'),
            'delete_task' => $user->hasPermissionTo('delete_task'),
        ];
    }

    protected function isRegularEntertainmentUser($user): bool
    {
        return $user->hasRole(BaseRole::Entertainment->value) ? true : false;
    }
}
