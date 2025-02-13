<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\TaskSongStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntertainmentTaskSongFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\EntertainmentTaskSong::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'status' => TaskSongStatus::Active->value,
            'employee_id' => 1,
            'project_id' => 1
        ];
    }
}

