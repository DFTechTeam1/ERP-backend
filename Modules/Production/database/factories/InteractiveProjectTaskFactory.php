<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Interactive\InteractiveTaskStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\InteractiveProjectTaskDeadline;
use Modules\Production\Models\InteractiveProjectTaskPicHistory;
use Modules\Production\Models\InteractiveProjectTaskPicWorkstate;

class InteractiveProjectTaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\InteractiveProjectTask::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $project = InteractiveProject::factory()
            ->withPics()
            ->withBoards()
            ->create();

        return [
            'intr_project_id' => $project->id,
            'intr_project_board_id' => $project->boards[0]->id,
            'name' => fake()->sentence(1),
            'description' => fake()->text(),
            'deadline' => now()->addMonth()->format('Y-m-d H:i:s'),
            'status' => InteractiveTaskStatus::Draft->value,
        ];
    }

    public function withPic(
        ?string $deadline = null,
        bool $withWorkState = false,
        ?object $employee = null)
    {
        return $this->afterCreating(function (InteractiveProjectTask $task) use ($deadline, $withWorkState, $employee) {
            if (! $employee) {
                $employee = Employee::factory()
                    ->withUser()
                    ->create();
            }

            $task->pics()->create([
                'employee_id' => $employee->id,
            ]);

            // assign current pic id
            InteractiveProjectTask::where('id', $task->id)
                ->update([
                    'current_pic_id' => $employee->id,
                ]);

            InteractiveProjectTaskPicHistory::upsert(
                [
                    [
                        'task_id' => $task->id,
                        'employee_id' => $employee->id,
                        'is_until_finish' => 1,
                    ],
                ],
                [
                    'task_id',
                    'employee_id',
                ],
                [
                    'is_until_finish',
                ]
            );

            if ($deadline) {
                InteractiveProjectTaskDeadline::create([
                    'task_id' => $task->id,
                    'deadline' => $deadline,
                    'employee_id' => $employee->id,
                    'start_time' => $task->status == \App\Enums\Development\Project\Task\TaskStatus::InProgress ? Carbon::now() : null,
                ]);

                InteractiveProjectTask::where('id', $task->id)
                    ->update([
                        'deadline' => $deadline,
                    ]);
            }

            if ($withWorkState) {
                InteractiveProjectTaskPicWorkstate::create([
                    'task_id' => $task->id,
                    'employee_id' => $employee->id,
                    'started_at' => Carbon::now(),
                ]);
            }
        });
    }

    public function withApprovalState()
    {
        return $this->afterCreating(function (InteractiveProjectTask $task) {
            $task->load(['interactiveProject.pics', 'workStates']);

            $workState = $task->workStates->firstWhere('complete_at', null);
            $haveWorkstate = $workState ? true : false;

            if ($task->interactiveProject->pics->isNotEmpty() && $haveWorkstate) {
                foreach ($task->interactiveProject->pics as $pic) {
                    $task->approvalStates()->create([
                        'pic_id' => $pic->employee_id,
                        'project_id' => $task->intr_project_id,
                        'task_id' => $task->id,
                        'started_at' => Carbon::now(),
                        'work_state_id' => $workState->id,
                    ]);
                }
            }
        });
    }

    public function withHoldState()
    {
        return $this->afterCreating(function (InteractiveProjectTask $task) {
            foreach ($task->pics as $pic) {
                // get current workstate
                $workState = InteractiveProjectTaskPicWorkstate::where('task_id', $task->id)
                    ->where('employee_id', $pic->employee_id)
                    ->first();

                $task->holdStates()->create([
                    'employee_id' => $pic->employee_id,
                    'holded_at' => Carbon::now(),
                    'work_state_id' => $workState ? $workState->id : null,
                    'reason' => 'holding on',
                ]);

                if ($task->status == InteractiveTaskStatus::InProgress) {
                    $task->status = InteractiveTaskStatus::OnHold;
                    $task->save();
                }
            }
        });
    }
}
