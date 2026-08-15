<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\EventType;
use App\Enums\Production\ProjectLeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectLead;

class ProjectLeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProjectLead::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'uid' => Str::uuid()->toString(),
            'name' => fake()->name(),
            'customer_phone' => '628'.fake()->numerify('##########'),
            'project_date' => date('Y-m-d', strtotime('+1 week')),
            'event_type' => fake()->randomElement(EventType::cases())->value,
            'venue' => 'Hotel Brawijaya',
            'city_id' => null,
            'pic_id' => null,
            'collaboration' => 'nuansa',
            'note' => fake()->sentence(),
            'total_led' => '4',
            'led_detail' => '[{"name":"main","total":4,"totalRaw":4,"textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]}]',
            'project_class_id' => ProjectClass::factory(),
            'created_by' => Employee::factory(),
            'updated_by' => null,
            'is_final' => false,
            'cell_options' => null,
            'skip_check' => false,
            'project_deal_id' => null,
            'status' => ProjectLeadStatus::ACTIVE->value,
        ];
    }

    /**
     * A lead holding only the columns the table requires, used to contrast with
     * a fully filled duplicate.
     */
    public function bare(): static
    {
        return $this->state(fn () => [
            'customer_phone' => null,
            'event_type' => null,
            'venue' => null,
            'city_id' => null,
            'pic_id' => null,
            'collaboration' => null,
            'note' => null,
            'total_led' => null,
            'led_detail' => null,
            'project_class_id' => null,
            'cell_options' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ProjectLeadStatus::CANCELLED->value,
            'cancel_reason' => 'Client postponed the event',
            'cancel_at' => now(),
        ]);
    }
}
