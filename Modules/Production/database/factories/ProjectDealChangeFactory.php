<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\ProjectDealChangeStatus;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;

class ProjectDealChangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectDealChange::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = 'Project Name Deal';

        $projectDeal = ProjectDeal::factory()
            ->create([
                'status' => ProjectDealStatus::Final->value,
                'name' => $name
            ]);

        Project::factory()
            ->create([
                'name' => $name,
                'project_deal_id' => $projectDeal->id,
                'status' => ProjectStatus::Draft->value
            ]);

        return [
            'project_deal_id' => $projectDeal->id,
            'detail_changes' => [
                [
                    'label' => 'Name',
                    'old_value' => $name,
                    'new_value' => $name . ' Update',
                ]
            ],
            'status' => ProjectDealChangeStatus::Pending->value,
            'requested_at' => Carbon::now(),
            'requested_by' => null
        ];
    }
}

