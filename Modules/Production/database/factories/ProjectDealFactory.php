<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\EventType;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;

class ProjectDealFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectDeal::class;

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
        $eventTypes = $this->formatCollectionEnum(EventType::cases());

        $customer = Customer::factory()->create();

        return [
            'name' => fake()->name(),
            'project_date' => date('Y-m-d', strtotime('+1 week')),
            'customer_id' => $customer->id,
            'event_type' => fake()->randomElement($eventTypes),
            'venue' => 'Hotel brawijaya',
            'collaboration' => 'nuansa',
            'led_area' => 1,
            'led_detail' => '[{"name":"main","total":4,"totalRaw":4,"textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]},{"name":" prefunction","total":1,"totalRaw":1,"textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]',
            'country_id' => Province::factory()->create()->code,
            'state_id' => IndonesiaCity::factory()->create()->code,
            'city_id' => IndonesiaDistrict::factory()->create()->code,
            'project_class_id' => ProjectClass::factory()->create()->id,
            'equipment_type' => 'others',
            'is_high_season' => false,
            'longitude' => fake()->longitude(),
            'latitude' => fake()->latitude(),
            'status' => ProjectDealStatus::Draft->value
        ];
    }
}

