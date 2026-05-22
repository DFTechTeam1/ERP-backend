<?php

namespace Modules\Hrd\Database\Factories;

use App\Enums\Employee\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\Position;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\GreatdayCostCenter;
use Modules\Hrd\Models\GreatdayWorkLocation;

class EmployeeTransferHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Hrd\Models\EmployeeTransferHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $employee = Employee::factory()
                ->withUser()
                ->state([
                    'status' => Status::Permanent->value,
                    'employment_status_id' => EmploymentStatus::factory()
                        ->state([
                            'is_terminal' => 0,
                        ]),
                    'greatday_work_location' => function () {
                        return GreatdayWorkLocation::factory()->create()->code;
                    },
                    'greatday_cost_center' => function () {
                        return GreatdayCostCenter::factory()->create()->code;
                    }
                ]);

        return [
            'employee_id' => $employee,
            'from_position_id' => function ($attributes) {
                return Employee::find($attributes['employee_id'])?->position_id ?? 0;
            },
            'from_position_name' => function ($attributes) {
                return Position::find($attributes['from_position_id'])?->name ?? '';
            },
            'to_position_id' => Position::factory(),
            'to_position_name' => function ($attributes) {
                return Position::find($attributes['to_position_id'])?->name ?? '';
            },
            'transfer_type' => 'TERMINATION',
            'from_work_location_id' => function ($attributes) {
                return GreatdayWorkLocation::select('id')
                    ->where('code', Employee::select('greatday_work_location')->find($attributes['employee_id'])?->greatday_work_location ?? '')
                    ->first()?->id ?? 0;
            },
            'from_work_location_name' => function ($attributes) {
                return GreatdayWorkLocation::select('id')
                    ->where('name', Employee::select('greatday_work_location')->find($attributes['employee_id'])?->greatday_work_location ?? '')
                    ->first()?->name ?? 0;
            },
            'to_work_location_id' => GreatdayWorkLocation::factory(),
            'to_work_location_name' => function ($attributes) {
                return GreatdayWorkLocation::find($attributes['to_work_location_id'])?->id ?? 0;
            },
            'from_cost_center_id' => function ($attributes) {
                return GreatdayCostCenter::select('id')
                    ->where('code', Employee::select('greatday_cost_center')->find($attributes['employee_id'])?->greatday_cost_center ?? '')
                    ->first()?->id ?? 0;
            },
            'from_cost_center_name' => function ($attributes) {
                return GreatdayCostCenter::select('name_en')
                    ->where('code', Employee::select('greatday_cost_center')->find($attributes['employee_id'])?->greatday_cost_center ?? '')
                    ->first()?->name_en ?? 0;
            },
            'to_cost_center_id' => GreatdayCostCenter::factory(),
            'to_cost_center_name' => function ($attributes) {
                return GreatdayCostCenter::find($attributes['to_cost_center_id'])?->name ?? '';
            },
            'to_boss_id' => '',
            'to_boss_name' => '',
            'from_boss_id' => '',
            'from_boss_name' => '',
        ];
    }
}

