<?php

namespace App\Actions;

use App\Enums\Production\ProjectStatus;
use App\Services\GeneralService;
use App\Services\Geocoding;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectRepository;

class CopyDealToProject
{
    use AsAction;

    public function handle(object $projectDeal, GeneralService $generalService, bool $isHaveInteractiveElement = false)
    {
        $geocoding = new Geocoding();
        if ($projectDeal->city && $projectDeal->state) {
            $coordinate = $geocoding->getCoordinate($projectDeal->city->name.', '.$projectDeal->state->name);
            if (count($coordinate) > 0) {
                $longitude = $coordinate['longitude'];
                $latitude = $coordinate['latitude'];
            }
        }

        $projectRepo = new ProjectRepository();
        $project = $projectRepo->store(data: [
            'name' => $projectDeal->name,
            'client_portal' => config('app.frontend_url') . '/' . $generalService->linkShortener(length: 10),
            'project_date' => $projectDeal->project_date,
            'event_type' => $projectDeal->event_type,
            'venue' => $projectDeal->venue,
            'collaboration' => $projectDeal->collaboration,
            'note' => $projectDeal->note,
            'status' => ProjectStatus::Draft->value,
            'classification' => $projectDeal->class->name,
            'led_area' => $projectDeal->led_area,
            'led_detail' => json_encode($projectDeal->led_detail),
            'country_id' => $projectDeal->country_id,
            'state_id' => $projectDeal->state_id,
            'city_id' => $projectDeal->city_id,
            'city_name' => $projectDeal->city ? $projectDeal->city->name : null,
            'project_class_id' => $projectDeal->project_class_id,
            'longitude' => $longitude ?? null,
            'latitude' => $latitude ?? null,
            'project_deal_id' => $projectDeal->id
        ]);

        $project->marketings()->createMany(
            collect($projectDeal->marketings)->map(function ($marketing) {
                return [
                    'marketing_id' => $marketing->employee_id
                ];
            })->toArray()
        );

        // create boards data
        $defaultBoards = json_decode($generalService->getSettingByKey('default_boards'), true);
        
        $defaultBoards = collect($defaultBoards)->map(function ($item) {
            return [
                'based_board_id' => $item['id'],
                'sort' => $item['sort'],
                'name' => $item['name'],
            ];
        })->values()->toArray();
        if ($defaultBoards) {
            $project->boards()->createMany($defaultBoards);
        }

        return $project;
    }
}
