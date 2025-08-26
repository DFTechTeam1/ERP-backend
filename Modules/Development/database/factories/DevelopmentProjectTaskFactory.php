<?php

namespace Modules\Development\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTaskDeadline;
use Modules\Development\Models\DevelopmentProjectTaskPicHistory;
use Modules\Development\Models\DevelopmentProjectTaskPicWorkstate;
use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Database\Eloquent\Collection;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Hrd\Models\Employee;

class DevelopmentProjectTaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Development\Models\DevelopmentProjectTask::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $project = DevelopmentProject::factory()
            ->withBoards()
            ->withPics()
            ->create();

        return [
            'development_project_id' => $project->id,
            'development_project_board_id' => $project->boards->first()->id,
            'name' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'deadline' => null,
            'status' => TaskStatus::InProgress->value,
            'current_pic_id' => null
        ];
    }

    public function withPic(?string $deadline = null, bool $withWorkState = false, ?object $employee = null)
    {
        return $this->afterCreating(function (DevelopmentProjectTask $task) use ($deadline, $withWorkState, $employee) {
            if (!$employee) {
                $employee = Employee::factory()
                    ->withUser()
                    ->create();
            }
            
            $task->pics()->create([
                'employee_id' => $employee->id
            ]);

            // assign current pic id
            DevelopmentProjectTask::where('id', $task->id)
                ->update([
                    'current_pic_id' => $employee->id
                ]);

            DevelopmentProjectTaskPicHistory::upsert(
                [
                    [
                        'task_id' => $task->id,
                        'employee_id' => $employee->id,
                        'is_until_finish' => 1
                    ]
                ],
                [
                    'task_id',
                    'employee_id'
                ],
                [
                    'is_until_finish'
                ]
            );

            if ($deadline) {
                DevelopmentProjectTaskDeadline::create([
                    'task_id' => $task->id,
                    'deadline' => $deadline,
                    'employee_id' => $employee->id,
                    'start_time' => $task->status == \App\Enums\Development\Project\Task\TaskStatus::InProgress ? Carbon::now() : null
                ]);
            }

            if ($withWorkState) {
                DevelopmentProjectTaskPicWorkstate::create([
                    'task_id' => $task->id,
                    'employee_id' => $employee->id,
                    'started_at' => Carbon::now()
                ]);
            }
        });
    }

    public function withHoldState()
    {
        return $this->afterCreating(function (DevelopmentProjectTask $task) {
            foreach ($task->pics as $pic) {
                // get current workstate
                $workState = DevelopmentProjectTaskPicWorkstate::where('task_id', $task->id)
                    ->where('employee_id', $pic->employee_id)
                    ->first();

                $task->holdStates()->create([
                    'employee_id' => $pic->employee_id,
                    'holded_at' => Carbon::now(),
                    'work_state_id' => $workState ? $workState->id : null
                ]);

                if ($task->status == TaskStatus::InProgress) {
                    $task->status = TaskStatus::OnHold;
                    $task->save();
                }
            }
        });
    }
}

