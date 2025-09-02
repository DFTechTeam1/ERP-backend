<?php

namespace Modules\Production\Console;

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Console\Command;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SynchronizeEventDealsWithProduction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:connecting-event-deals';

    /**
     * The console command description.
     */
    protected $description = 'This command used to connecting event deals with production event via name and project_date';

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
        // connecting projects
        $finalProjects = ProjectDeal::selectRaw('id,name,project_date')
            ->where('status', ProjectDealStatus::Final)
            ->get();

        foreach ($finalProjects as $finalProject) {
            $project = Project::selectRaw('id,name,project_date')
                ->whereNull('project_deal_id')
                ->where('name', $finalProject->name)
                ->where('project_date', $finalProject->project_date)
                ->first();

            if ($project) {
                Project::where('id', $project->id)
                    ->update([
                        'project_deal_id' => $finalProject->id,
                    ]);

                $this->info("{$project->name} has been synchronize with deal {$finalProject->name}");
            }
        }
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
