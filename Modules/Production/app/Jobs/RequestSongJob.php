<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\RequestSongNotification;

class RequestSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    public $songs;

    public $createdBy;

    /**
     * Create a new job instance.
     */
    public function __construct(\Modules\Production\Models\Project $project, array $songs, int $createdBy)
    {
        $this->project = $project;

        $this->songs = $songs;

        $this->createdBy = $createdBy;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get entertain pm
        $entertainmentPic = \App\Models\User::role('project manager entertainment')
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if (
            ($entertainmentPic) &&
            (
                ($entertainmentPic->employee) &&
                ($entertainmentPic->employee->telegram_chat_id)
            )
        ) {
            $requester = Employee::selectRaw('id,nickname')
                ->where('user_id', $this->createdBy)
                ->first();

            $message = "Halo " . $entertainmentPic->employee->nickname;
            $message .= "\n" . $requester->nickname . " telah menambahkan list lagu di event " . $this->project->name;

            $message .= "\nLagu untuk event ini adalah:\n";

            $songs = ProjectSongList::select('name')
                ->where('project_id', $this->project->id)
                ->get();

            foreach ($songs as $keySong => $song) {
                $index = $keySong + 1;
                $message .= "{$index}. {$song->name}\n";
            }

            $entertainmentPic->notify(new RequestSongNotification(
                telegramChatIds: [$entertainmentPic->employee->telegram_chat_id],
                message: $message
            ));
        }
    }
}
