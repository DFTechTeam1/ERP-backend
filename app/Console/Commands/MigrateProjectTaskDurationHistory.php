<?php

namespace App\Console\Commands;

use App\Enums\Production\TaskHistoryType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Production\Models\ProjectTaskDurationHistory;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;

class MigrateProjectTaskDurationHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-project-task-duration-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write task duration history from existing task.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectTaskPicLogRepo = new ProjectTaskPicLogRepository;
        $projectRepo = new ProjectRepository;

        $projects = $projectRepo->list(
            select: 'id',
            relation: [
                'tasks:id,project_id',
            ]
        );

        Schema::disableForeignKeyConstraints();
        DB::table('project_task_duration_histories')->truncate();
        Schema::enableForeignKeyConstraints();

        $progressBar = $this->output->createProgressBar($projects->count());
        foreach ($projects as $project) {
            foreach ($project->tasks as $task) {
                $projectTaskId = $task->id;

                $tasks = $projectTaskPicLogRepo->list(
                    select: 'id,employee_id,project_task_id,time_added,work_type',
                    where: "project_task_id = {$projectTaskId}",
                    orderBy: 'time_added ASC',
                    relation: [
                        'task:id,project_id',
                        'task.project:id',
                        'task.project.personInCharges',
                        'employee:id,name',
                    ]
                );

                if (count($tasks) > 0) {
                    $pm = collect($tasks[0]->task->project->personInCharges)->pluck('pic_id')->toArray();

                    if (isset($pm[0])) {
                        $output = $tasks->filter(function ($filter) use ($pm) {
                            return ! in_array($filter->employee_id, $pm);
                        })->values()->map(function ($mapping) {
                            return [
                                'id' => $mapping->id,
                                'employee_id' => $mapping->employee_id,
                                'project_task_id' => $mapping->project_task_id,
                                'time_added' => $mapping->time_added,
                                'work_type' => $mapping->work_type,
                                'employee' => $mapping->employee->name,
                                'project_id' => $mapping->task->project_id,
                            ];
                        })->groupBy('employee_id')->toArray();
    
                        // format output to be filled to the new table
                        foreach ($output as $employeeId => $dataGroup) {
                            $start = Carbon::parse($dataGroup[0]['time_added']);
                            $end = Carbon::parse($dataGroup[count($dataGroup) - 1]['time_added']);
                            $duration = $start->diffInSeconds($end);
    
                            $payload = [
                                'project_id' => $dataGroup[0]['project_id'],
                                'task_id' => $dataGroup[0]['project_task_id'],
                                'pic_id' => $pm[0],
                                'employee_id' => $employeeId,
                                'task_full_duration' => $duration,
                                'task_approval_duration' => 0,
                                'task_type' => 'production',
                                'created_at' => Carbon::now(),
                            ];
    
                            ProjectTaskDurationHistory::create($payload);
                        }
                    }
                }
            }

            $progressBar->advance();

            // $this->info("{$project->tasks->count()} has been migrated");
        }
        $progressBar->finish();
    }
}
