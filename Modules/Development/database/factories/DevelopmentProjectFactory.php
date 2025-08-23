<?php

namespace Modules\Development\Database\Factories;

use App\Enums\Development\Project\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Development\Models\DevelopmentProject;
use Modules\Hrd\Models\Employee;

class DevelopmentProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Development\Models\DevelopmentProject::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $employee = Employee::factory()->withUser()->create();

        $employee = Employee::with('user')->where('id', $employee->id)->first();

        return [
            'uid' => $this->faker->uuid(),
            'name' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => ProjectStatus::Active->value,
            'project_date' => $this->faker->date(),
            'created_by' => $employee->user->id
        ];
    }

    public function withPics(bool $withRealEmployee = false)
    {
        return $this->afterCreating(function (DevelopmentProject $project) use ($withRealEmployee) {
            // create pics data by do factory on employee model
            if ($withRealEmployee) {
                $employee = Employee::latest()->first();
            } else {
                $employee = Employee::factory()->withUser()->create();
            }

            $project->pics()->create([
                'employee_id' => $employee->id,
            ]);
        });
    }
}

