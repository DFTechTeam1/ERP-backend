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
        // $this->call([]);

        $projectManagerPositions = json_decode(getSettingByKey('position_as_project_manager'), true);
        $marketingPositions = getSettingByKey('position_as_marketing');
        $marketingPositions = getIdFromUid($marketingPositions, new \Modules\Company\Models\Position());
        
        if ($projectManagerPositions && $marketingPositions) {
            $projectManagerPositions = collect($projectManagerPositions)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\Position());
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

            $classification = \App\Enums\Production\Classification::cases();
            $classification = collect($classification)->map(function ($item) {
                return $item->value;
            })->toArray();

            $projects = [
                [
                    'name' => 'Wedding Arya',
                    'client_portal' => 'wedding-arya',
                    'marketing_id' => $marketings,
                    'project_date' => date('Y-m-d', strtotime('2024-07-20')),
                    'event_type' => fake()->randomElement($eventTypes),
                    'venue' => 'Hotel Samudra Makassar',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'status' => 1,
                    'classification' => fake()->randomElement($classification),
                    'led_area' => '37',
                    'led' => [
                        ['width' => 4, 'height' => 3],
                        ['width' => 5, 'height' => 5],
                    ],
                    'pic' => [
                        fake()->randomElement($projectManagers),
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Birthday Superman',
                    'client_portal' => 'birthday-supermane',
                    'marketing_id' => $marketings,
                    'project_date' => date('Y-m-d', strtotime('2024-07-22')),
                    'event_type' => fake()->randomElement($eventTypes),
                    'venue' => 'Hotel Cleo',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'status' => 1,
                    'classification' => fake()->randomElement($classification),
                    'led_area' => '37',
                    'led' => [
                        ['width' => 4, 'height' => 3],
                        ['width' => 5, 'height' => 5],
                    ],
                    'pic' => [
                        fake()->randomElement($projectManagers),
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'Engagment Siska',
                    'client_portal' => 'eng-siska',
                    'marketing_id' => $marketings,
                    'project_date' => date('Y-m-d', strtotime('2024-07-25')),
                    'event_type' => fake()->randomElement($eventTypes),
                    'venue' => 'Hotel Mutiara Malang',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'status' => 1,
                    'classification' => fake()->randomElement($classification),
                    'led_area' => '37',
                    'led' => [
                        ['width' => 4, 'height' => 3],
                        ['width' => 5, 'height' => 5],
                    ],
                    'pic' => [
                        fake()->randomElement($projectManagers),
                    ],
                    'seeder' => true,
                ],
                [
                    'name' => 'BUMN Meeting',
                    'client_portal' => 'bumn-meeting',
                    'marketing_id' => $marketings,
                    'project_date' => date('Y-m-d', strtotime('2024-07-25')),
                    'event_type' => fake()->randomElement($eventTypes),
                    'venue' => 'Hotel Jayapura',
                    'collaboration' => 'nuansa',
                    'note' => '',
                    'status' => 1,
                    'classification' => fake()->randomElement($classification),
                    'led_area' => '37',
                    'led' => [
                        ['width' => 4, 'height' => 3],
                        ['width' => 5, 'height' => 5],
                    ],
                    'pic' => [
                        fake()->randomElement($projectManagers),
                    ],
                    'seeder' => true,
                ],
            ];

            $projectService = new \Modules\Production\Services\ProjectService();

            foreach ($projects as $project) {
                $store = $projectService->store($project);

                $this->command->info('store result: ' . json_encode($store));
            }
        }

    }
}
