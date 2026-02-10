<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AddingPrefixInProjectIdentifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prefix-project-identifier';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adding P- prefix in project identifier IDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get projects that already have identifier_id but missing the "P-" prefix
        $projects = \Modules\Production\Models\Project::selectRaw('id')
            ->whereNotNull('identifier_id')
            ->where('identifier_id', 'NOT LIKE', 'P-%')
            ->get();

        // Looping and update current identifier
        foreach ($projects as $project) {
            $projectModel = \Modules\Production\Models\Project::find($project->id);
            $projectModel->identifier_id = 'P-' . $projectModel->identifier_id;
            $projectModel->save();

            $this->info("Updated Project ID {$project->id} with new Identifier ID: {$projectModel->identifier_id}");
        }
    }
}
