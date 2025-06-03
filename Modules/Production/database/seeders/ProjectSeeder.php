<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projectService = new \Modules\Production\Services\ProjectService;

        // delete current project
        $projects = \Modules\Production\Models\Project::select('uid')->get();
        $projectUids = collect((object) $projects)->pluck('uid')->toArray();
        $projectService->bulkDelete($projectUids);

        $projectManagerPositions = json_decode(getSettingByKey('position_as_project_manager'), true);
        $marketingPositions = getSettingByKey('position_as_marketing');
        $marketingPositions = getIdFromUid($marketingPositions, new \Modules\Company\Models\PositionBackup);

        if ($projectManagerPositions && $marketingPositions) {
            $projectManagerPositions = collect($projectManagerPositions)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
            })->toArray();

            $marketings = \Modules\Hrd\Models\Employee::selectRaw('uid')
                ->where('position_id', $marketingPositions)
                ->get();
            $marketings = collect($marketings)->pluck('uid')->toArray();

            $projectManagers = \Modules\Hrd\Models\Employee::selectRaw('uid')
                ->whereIn('position_id', $projectManagerPositions)
                ->get();
            $projectManagers = collect($projectManagers)->pluck('uid')->toArray();

            $eventTypes = \App\Enums\Production\EventType::cases();
            $eventTypes = collect($eventTypes)->map(function ($item) {
                return $item->value;
            })->toArray();

            $classification = \Modules\Company\Models\ProjectClass::selectRaw('id,name')
                ->get();
            foreach ($classification as $class) {
                if (strtolower($class->name) == 'a (big)') {
                    $aClass = $class->name;
                    $aClassId = $class->id;
                }
                if (strtolower($class->name) == 's (special)') {
                    $sClass = $class->name;
                    $sClassId = $class->id;
                }
                if (strtolower($class->name) == 'c (budget)') {
                    $cClass = $class->name;
                    $cClassId = $class->id;
                }
                if (strtolower($class->name) == 'b (standard)') {
                    $bClass = $class->name;
                    $bClassId = $class->id;
                }
            }

            $projectDates = [
                date('Y-m-d', strtotime('+20 days')),
                date('Y-m-d', strtotime('+22 days')),
                date('Y-m-d', strtotime('+24 days')),
                date('Y-m-d', strtotime('+26 days')),
                date('Y-m-d', strtotime('+26 days')),
                date('Y-m-d', strtotime('+26 days')),
                date('Y-m-d', strtotime('+26 days')),
                date('Y-m-d', strtotime('+26 days')),
                date('Y-m-d', strtotime('+28 days')),
                date('Y-m-d', strtotime('+30 days')),
                date('Y-m-d', strtotime('+32 days')),
                date('Y-m-d', strtotime('+34 days')),
                date('Y-m-d', strtotime('+36 days')),
                date('Y-m-d', strtotime('+38 days')),
                date('Y-m-d', strtotime('+40 days')),
                date('Y-m-d', strtotime('+42 days')),
            ];

            $city = \Modules\Company\Models\City::whereRaw("lower(name) = 'surabaya'")
                ->first();

            $projects = [
                [
                    'name' => 'Andrew Cindy',
                    'client_portal' => 'andrew-cindy',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'JW Marriott',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $cClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Kevin Jessica',
                    'client_portal' => 'kevin-jessica',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Hotel Moda',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $cClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Erline Sweet17',
                    'client_portal' => 'erline-sweet17',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Hotel Grand Mercure',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $aClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Edward Natasha',
                    'client_portal' => 'edward-natasha',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Hotel Ramayana',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $aClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'wedding anniversary',
                    'client_portal' => 'wedding-anniversary',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Shangrila',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $bClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'SAAT',
                    'client_portal' => 'saat',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Hotel Malang',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $cClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Wendy Carissa',
                    'client_portal' => 'wendy-carissa',
                    'marketing_id' => [fake()->randomElement($marketings)],
                    'project_date' => fake()->randomElement($projectDates),
                    'event_type' => fake()->randomElement($eventTypes),
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'venue' => 'Hotel Pekanbaru',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'classification' => $aClassId,
                    'status' => null,
                    'led_area' => '2',
                    'led_detail' => [
                        [
                            'led' => [
                                [
                                    'height' => 1,
                                    'width' => 2,
                                ],
                            ],
                            'name' => 'main',
                            'textDetail' => '1 x 2 m',
                            'total' => '2 m<sup>2</sup>',
                            'totalRaw' => '2',
                        ],
                    ],
                    'seeder' => true,
                ],
            ];

            foreach ($projects as $project) {
                $store = $projectService->store($project);

                $this->command->info('store result: '.json_encode($store));
            }
        }

    }
}
