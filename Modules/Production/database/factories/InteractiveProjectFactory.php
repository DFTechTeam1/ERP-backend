<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Interactive\InteractiveProjectStatus;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;

class InteractiveProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\InteractiveProject::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $projectDeal = ProjectDeal::factory()
            ->create([
                'status' => ProjectDealStatus::Final->value,
            ]);

        $project = Project::factory()
            ->create([
                'name' => fake()->sentence(1),
                'project_deal_id' => $projectDeal->id,
                'status' => ProjectStatus::Draft->value,
            ]);

        return [
            'name' => fake()->sentence(1),
            'client_portal' => fake()->url(),
            'parent_project' => $project->id,
            'project_date' => $project->project_date,
            'event_type' => $project->event_type,
            'venue' => $project->venue,
            'marketing_id' => null,
            'collaboration' => $project->collaboration,
            'status' => InteractiveProjectStatus::Draft->value,
            'classification' => $project->classification,
            'note' => $project->note,
            'led_area' => $project->led_area,
            'led_detail' => $project->led_detail,
            'project_class_id' => $project->project_class_id,
        ];
    }

    public function withBoards()
    {
        return $this->afterCreating(function (\Modules\Production\Models\InteractiveProject $interactiveProject) {
            \Modules\Production\Models\InteractiveProjectBoard::factory()
                ->count(3)
                ->create([
                    'project_id' => $interactiveProject->id,
                ]);
        });
    }

    public function withPics(bool $withRealEmployee = false, mixed $employee = null)
    {
        return $this->afterCreating(function (InteractiveProject $project) use ($withRealEmployee, $employee) {
            // create pics data by do factory on employee model
            if (! $employee) {
                if ($withRealEmployee) {
                    $position = PositionBackup::where('name', 'like', '%project manager%')->first();
                    $employee = Employee::latest()->first();
                } else {
                    $employee = Employee::factory()->withUser()->create();
                }
            }

            $project->pics()->create([
                'employee_id' => $employee->id,
            ]);
        });
    }
}
