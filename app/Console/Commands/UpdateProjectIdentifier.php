<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateProjectIdentifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-project-identifier';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update projects to have unique identifier IDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Generate unique identifierId contain 4 charaters, contain number, uppercase, lowercase letter
        $identifier = generateUniqueIdentifierId();

        // Get all projects without identifierId
        $projects = \Modules\Production\Models\Project::whereNull('identifier_id')->get();

        foreach ($projects as $project) {
            // Ensure the identifier is unique
            while (\Modules\Production\Models\Project::where('identifier_id', $identifier)->exists()) {
                $identifier = generateUniqueIdentifierId();
            }

            $project->identifier_id = $identifier;
            $project->save();

            $this->info("Updated Project ID {$project->id} with Identifier ID: {$identifier}");
        }
    }

    public function generateUniqueIdentifierId()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $identifierId = '';

        for ($i = 0; $i < 4; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $identifierId .= $characters[$index];
        }

        return $identifierId;
    }
}
