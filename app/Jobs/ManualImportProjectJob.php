<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ManualImportProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projects;

    /**
     * Create a new job instance.
     */
    public function __construct(array $projects)
    {
        $this->projects = $projects;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $output = [];

        $dateKey = 2;
        $nameKey = 3;
        $marketingKey = 1;
        $eventTypeKey = 4;
        $countryKey = 5;
        $stateKey = 6;
        $cityKey = 7;
        $venueKey = 8;
        $picKey = 9;
        $collabKey = 10;
        $ledKey = 11;
        $statusKey = 13;
        $classKey = 15;
        $noteKey = 12;

        foreach ($this->projects as $key => $project) {
            if ($key != 0) {
                if ($project[$ledKey] != null && $project[$nameKey] != null && $project[$dateKey] != null) {
                    $marketing = [];
                    if (strtolower($project[$marketingKey]) == 'wesley') {
                        $marketingData = \Modules\Hrd\Models\Employee::selectRaw('id,uid')->where('email', 'wesleywiyadi@gmail.com')->first();
                    } else if (strtolower($project[$marketingKey]) == 'charles') {
                        $marketingData = \Modules\Hrd\Models\Employee::selectRaw('id,uid')->where('email', 'charleseduardo526@gmail.com')->first();
                    }

                    $marketing[] = $marketingData->uid;

                    $class = preg_replace('/\(\s*(.*?)\s*\)/', '($1)', $project[$classKey]);
                    $classData = \Modules\Company\Models\ProjectClass::selectRaw('id,name')
                        ->whereRaw("lower(name) = '" . strtolower($class) . "'")
                        ->first();

                    switch (strtolower($project[$eventTypeKey])) {
                        case 'pameran':
                            $eventType = \App\Enums\Production\EventType::Exhibition->value;
                            break;

                        case 'wedding':
                            $eventType = \App\Enums\Production\EventType::Wedding->value;
                            break;

                        case 'engagement':
                            $eventType = \App\Enums\Production\EventType::Engagement->value;
                            break;

                        case 'event':
                            $eventType = \App\Enums\Production\EventType::Event->value;
                            break;

                        case 'birthday':
                            $eventType = \App\Enums\Production\EventType::Birthday->value;
                            break;

                        case 'concert':
                            $eventType = \App\Enums\Production\EventType::Concert->value;
                            break;

                        case 'corporate':
                            $eventType = \App\Enums\Production\EventType::Corporate->value;
                            break;
                        
                        default:
                            $eventType = \App\Enums\Production\EventType::Exhibition->value;
                            break;
                    }

                    $city = \Modules\Company\Models\City::select('id')->where('name', $project[$cityKey])->first();
                    $state = \Modules\Company\Models\State::select('id')->where('name', $project[$stateKey])->first();
                    $country = \Modules\Company\Models\Country::select('id')->where('name', $project[$countryKey])->first();

                    $led = $this->formatLed($project[$ledKey]);
                    $ledTotal = collect($led)->pluck('totalRaw')->sum();

                    $output[] = [
                        'name' => $project[$nameKey],
                        'client_portal' => str_replace(' ', '-', $project[$nameKey]),
                        'marketing_id' => $marketing,
                        'project_date' => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $project[2])->format('Y-m-d'),
                        'event_type' => $eventType,
                        'city_id' => $city ? $city->id : $project[$cityKey],
                        'state_id' => $state ? $state->id : $project[$stateKey],
                        'country_id' => $country ? $country->id : $project[$countryKey],
                        'venue' => $project[$venueKey],
                        'collaboration' => $project[$collabKey],
                        'note' => $project[$noteKey],
                        'classification' => $classData ? $classData->id : $project[$classKey],
                        'status' => null,
                        'led_area' => $ledTotal,
                        'led_detail' => $led,
                        'seeder' => true,
                    ];
                }
            }
        }

        $projectService = new \Modules\Production\Services\ProjectService();

        $chunks = collect($output)->chunk(30)->toArray();

        foreach ($chunks as $seperated) {
            foreach ($seperated as $project) {
                $store = $projectService->store($project);

                if ($store['error']) {
                    logging('error', ['name' => $project['name'], 'error' => $store]);
                }
            }
        }
    }

    protected function formatLed(string $ledString)
    {
        $exp = explode(', ', $ledString);

        $led = [];
        foreach ($exp as $key => $detail) {
            $expName = explode(' - ', $detail);

            if (isset($expName[1])) {
                $led[$key] = [
                    'name' => $expName[0],
                    'total' => '',
                    'totalRaw' => '',
                    'textDetail' => '',
                    'led' => [],
                ];

                $expSize = explode('+', $expName[1]);
                foreach ($expSize as $keySize => $size) {
                    $exp1 = explode('*', $size);

                    if (count($exp1) > 1) {
                        $sizeString = '';
                        for ($a = 0; $a < $exp1[1]; $a++) {
                            $sizeString .= '+' . $exp1[0];
                        }

                        $sizeString = ltrim($sizeString, '+');

                        $expSize[$keySize] = $sizeString;
                    }
                }

                $ledFinal = [];
                $total = [];
                $textDetail = [];
                foreach ($expSize as $final) {
                    $finalExp = explode('+', $final);

                    foreach ($finalExp as $fe) {
                        $expFF = explode('x', $fe);
                        $width = $expFF[0];
                        $height = isset($expFF[1]) ? $expFF[1] : null;
                        $ledFinal[] = ['width' => $width, 'height' => $height];

                        $total[] = (float)$width * (float)$height;

                        $textDetail[] = $width . ' x ' . $height . ' m';
                    }
                }

                $led[$key]['led'] = $ledFinal ?? $project[$ledKey];
                $led[$key]['total'] = isset($total) ? array_sum($total) : 0;
                $led[$key]['totalRaw'] = isset($total) ? array_sum($total) : 0;
                $led[$key]['textDetail'] = isset($textDetail) ? implode(' , ', $textDetail) : 0;
            }
        }

        return $led;
    }
}
