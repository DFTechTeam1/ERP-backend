<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Production\Models\Project;

class ReassignProjectBoard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reassign-project-board';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projects = Project::whereDoesntHave('boards')
            ->get();

        $defaultBoards = json_decode(getSettingByKey('default_boards'), true);
        $defaultBoards = collect($defaultBoards)->map(function ($item) {
            return [
                'based_board_id' => $item['id'],
                'sort' => $item['sort'],
                'name' => $item['name'],
            ];
        })->values()->toArray();

        foreach ($projects as $project) {
            if ($defaultBoards) {
                $project->boards()->createMany($defaultBoards);

                // clear cache
                Cache::forget('detailProject'.$project->id);
            }
        }

        $this->info('Project board has been updated successfully');
    }
}
