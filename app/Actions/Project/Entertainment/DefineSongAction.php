<?php

namespace App\Actions\Project\Entertainment;

use App\Enums\Production\TaskSongStatus;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\ProjectSongList;

class DefineSongAction
{
    use AsAction;

    private readonly ProjectSongList $song;

    private readonly User $user;

    /**
     * Define rules user can edit the song or not
     * Only Root, Director, Project PIC, PM Entertainment can edit the song
     */
    protected function canEdit(): bool
    {
        $output = false;

        if ($this->user->hasPermissionTo('edit_request_song')) {
            $output = true;
        }

        return $output;
    }

    /**
     * Define rules user can delete song or not
     * Only Root, Director, Project PIC, PM Entertainment can delete the song
     */
    protected function canDelete(): bool
    {
        $output = false;

        if ($this->user->hasPermissionTo('delete_request_song')) {
            $output = true;
        }

        return $output;
    }

    /**
     * Define rules user can distribute this song to the team or not
     * Only for Root, Directory, Project PIC, PM Entertainment
     */
    protected function canDistribute(): bool
    {
        $output = false;

        if ($this->user->hasPermissionTo('distribute_request_song')) {
            $output = true;
        }

        if ($this->song->task && $this->song->task->status == TaskSongStatus::OnProgress->value) {
            $output = false;
        }

        return $output;
    }

    protected function canStartWork(): bool
    {
        $output = false;

        if ($this->user->haspermissionto('approve_request_song')) {
            $output = true;
        }

        return $output;
    }

    protected function canApproveWork(): bool
    {
        $output = false;

        if ($this->user->haspermissionto('approve_request_song')) {
            $output = true;
        }

        return $output;
    }

    public function handle(ProjectSongList $song, User $user): array
    {
        $this->song = $song;
        $this->user = $user;

        $output = [
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'can_distribute' => $this->canDistribute(),
            'can_start_work' => $this->canStartWork(),
            'can_approve_work' => $this->canApproveWork(),
            'can_take_action' => true,
            'is_request_edit' => false,
            'is_request_delete' => false,
        ];

        return $output;
    }
}
