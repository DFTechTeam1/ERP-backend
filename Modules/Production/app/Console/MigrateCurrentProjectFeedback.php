<?php

namespace Modules\Production\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrateCurrentProjectFeedback extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:migrate-current-project-feedback';

    /**
     * The console command description.
     */
    protected $description = 'Migrate current project feedback data to new structure';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projects = \Modules\Production\Models\Project::selectRaw('id,feedback')
            ->with(['personInCharges:id,project_id,pic_id'])
            ->whereNotNull('feedback')
            ->whereDoesntHave('feedbacks')
            ->whereHas('personInCharges')
            ->where('feedback', '!=', '')
            ->get();

        $payload = [];
        foreach ($projects as $project) {
            $payload[] = [
                'project_id' => $project->id,
                'feedback' => $project->feedback,
                'pic_id' => $project->personInCharges->first()?->pic_id,
                'points' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($payload, $projects) {
            DB::table('project_feedback')->insert($payload);
        });

        $this->info(count($payload) . ' project feedback migrated successfully.');
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
