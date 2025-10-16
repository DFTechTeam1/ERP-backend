<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\EventType;
use App\Enums\Production\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;

class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\Project::class;

    protected function formatCollectionEnum(array $data)
    {
        return collect($data)->map(function ($item) {
            return $item->value;
        })->toArray();
    }

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $status = $this->formatCollectionEnum(ProjectStatus::cases());
        $eventTypes = $this->formatCollectionEnum(EventType::cases());

        return [
            'name' => fake()->name(),
            'client_portal' => 'client_portal_'.fake()->randomKey(),
            'project_date' => date('Y-m-d', strtotime('+1 week')),
            'event_type' => fake()->randomElement($eventTypes),
            'venue' => 'Hotel brawijaya',
            'marketing_id' => Employee::factory(),
            'collaboration' => 'nuansa',
            'status' => fake()->randomElement($status),
            'classification' => ProjectClass::factory()->create()->name,
            'led_area' => 1,
            'led_detail' => '[{"name":"main","total":4,"totalRaw":4,"textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]},{"name":" prefunction","total":1,"totalRaw":1,"textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]',
            'created_by' => 1,
            'showreels' => null,
            // 'country_id' => Province::factory()->create()->code,
            // 'state_id' => IndonesiaCity::factory()->create()->code,
            // 'city_id' => IndonesiaDistrict::factory()->create()->code,
            'city_name' => 'Surabaya',
            'project_class_id' => ProjectClass::factory(),
            'longitude' => '106.8277658',
            'latitude' => '-6.1875613',
            'project_deal_id' => null,
        ];
    }

    public function withBoards()
    {
        return $this->afterCreating(function (\Modules\Production\Models\Project $project) {
            \Modules\Production\Models\ProjectBoard::factory()
                ->count(4)
                ->create([
                    'project_id' => $project->id,
                ]);
        });
    }
}
