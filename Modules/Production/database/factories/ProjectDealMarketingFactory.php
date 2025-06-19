<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

class ProjectDealMarketingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectDealMarketing::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {

        return [
            'project_deal_id' => ProjectDeal::factory()->create()->id,
            'employee_id' => Employee::factory()->create()->id
        ];
    }
}

