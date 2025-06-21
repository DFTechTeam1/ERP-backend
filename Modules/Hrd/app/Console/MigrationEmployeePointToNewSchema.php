<?php

namespace Modules\Hrd\Console;

use App\Enums\Production\WorkType;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

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
        // first empty points table
        Schema::disableForeignKeyConstraints();
        $tables = ['employee_point_project_details', 'employee_point_projects', 'employee_points'];
        collect($tables)->each(function ($item) {
            DB::table($item)->truncate();
        });
        Schema::enableForeignKeyConstraints();

        DB::beginTransaction();

        $data = DB::table('project_task_pic_logs as l')
            ->selectRaw('DISTINCT l.project_task_id,l.employee_id,l.work_type,t.project_id,t.name as task_name,p.name as project_name,tp.point,tp.additional_point')
            ->join('project_tasks as t', 't.id', '=', 'l.project_task_id')
            ->join('projects as p', 'p.id', 't.project_id')
            ->join('employee_task_points as tp', function (JoinClause $join) {
                $join->on('tp.project_id', '=', 't.project_id')
                    ->on('tp.employee_id', '=', 'l.employee_id');
            })
            ->whereRaw("l.work_type = '".WorkType::Assigned->value."' AND tp.point > 0")
            ->get();

        // group by employee id then project id
        $groups = [];
        foreach ($data as $dataGroup) {
            $groups[$dataGroup->employee_id][] = $dataGroup;
        }

        $payload = [];

        // group 'deeper' by project id
        foreach ($groups as $employeeId => $detailPoint) {
            foreach ($detailPoint as $point) {
                $payload[$employeeId][$point->project_id][] = $point;
            }
        }

        // make format ready to store
        $format = [];
        $a = 0;
        foreach ($payload as $employeeId => $employeePoint) {
            $b = 0;

            $totalPoint = [];
            $pointProjects = [];
            $details = [];
            foreach ($employeePoint as $projectId => $detailPoint) {
                $totalPoint[] = count($detailPoint) + $detailPoint[0]->additional_point;

                $pointProjects[] = [
                    'project_id' => $projectId,
                    'total_point' => count($employeePoint[$projectId]) + $detailPoint[0]->additional_point,
                    'additional_point' => $detailPoint[0]->additional_point,
                ];

                foreach ($detailPoint as $point) {
                    $details = [
                        'task_id' => $point->project_task_id,
                    ];

                    $pointProjects[$b]['tasks'][] = $details;
                }

                $b++;
            }

            $format[] = [
                'employee_id' => $employeeId,
                'total_point' => collect($totalPoint)->sum(),
                'type' => 'production',
                'pointProjects' => $pointProjects,
            ];
            $a++;
        }

        // do migrate here
        try {

            foreach ($format as $readyPayload) {
                $employeePoint = EmployeePoint::create(
                    collect($readyPayload)->only([
                        'employee_id', 'total_point', 'type',
                    ])->toArray()
                );

                // employee point projects
                foreach ($readyPayload['pointProjects'] as $projectPayload) {
                    $projectPoint = EmployeePointProject::create(
                        collect($projectPayload)->except(['tasks'])->merge(['employee_point_id' => $employeePoint->id])->toArray()
                    );

                    // employee point project details
                    foreach ($projectPayload['tasks'] as $taskPoint) {
                        EmployeePointProjectDetail::create([
                            'task_id' => $taskPoint['task_id'],
                            'point_id' => $projectPoint->id,
                        ]);
                    }
                }
            }
            DB::commit();

            $this->info('Yeay! Migration is done!');
        } catch (\Throwable $th) {
            DB::rollBack();

            $this->error('Oops..... '.$th->getMessage());
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
