<?php

namespace Modules\Hrd\Console;

use App\Enums\Production\WorkType;
use Illuminate\Console\Command;
use Modules\Hrd\Models\EmployeeTaskPoint;
use Modules\Production\Models\ProjectTaskPicHistory;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrationEmployeePointToNewSchema extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:migration-employee-point';

    /**
     * The console command description.
     */
    protected $description = 'Migration from old table to new format';

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
        $currentPoints = EmployeeTaskPoint::all();

        $payload = [];
        foreach ($currentPoints as $key => $point) {
            $payload[] = $point;

            $taskHistories = ProjectTaskPicHistory::selectRaw('DISTINCT project_id,project_task_id,employee_id')
                ->where('project_id', $point->project_id)
                ->where('employee_id', $point->employee_id)
                ->get();

            $payload[$key]['tasks'] = $taskHistories;
        }

        $this->info(json_encode($payload));
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
