<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\EventType;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectStatus;
use App\Enums\Transaction\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

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
            'led_detail' => json_decode('[{"name":"main","total":4,"totalRaw":4,"textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]},{"name":" prefunction","total":1,"totalRaw":1,"textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]', true),
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

    public function withQuotation(int $price = 0)
    {
        return $this->afterCreating(function (ProjectDeal $projectDeal) use ($price) {
            $state = [
                'project_deal_id' => $projectDeal->id,
                'is_final' => 1,
            ];

            if ($price > 0) {
                $state['fix_price'] = $price;
            }

            \Modules\Production\Models\ProjectQuotation::factory()->withItems(0, $projectDeal->name)->create($state);
        });
    }

    public function withInvoice(int $numberOfInvoice = 1, array $rawData = [])
    {
        return $this->afterCreating(function (ProjectDeal $projectDeal) use ($numberOfInvoice, $rawData) {
            $invoice = \Modules\Finance\Models\Invoice::factory()->create([
                'project_deal_id' => $projectDeal->id,
                'status' => InvoiceStatus::Unpaid->value,
                'amount' => 100000000,
                'raw_data' => $rawData ?? null,
            ]);

            if ($numberOfInvoice > 1) {
                $state = [
                    'project_deal_id' => $projectDeal->id,  
                    'status' => InvoiceStatus::Unpaid->value,
                    'parent_number' => $invoice->number,
                    'number' => $invoice->number . "A",
                    'amount' => 100000000
                ];

                if (!empty($rawData)) {
                    $state['raw_data'] = $rawData;
                }

                \Modules\Finance\Models\Invoice::factory()->create($state);
            }
        });
    }
}

