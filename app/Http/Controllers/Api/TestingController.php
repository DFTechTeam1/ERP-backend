<?php

namespace App\Http\Controllers\Api;

use App\Events\TestingEvent;
use App\Exceptions\GenerateQrcodeError;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormInteractiveRequest;
use App\Http\Requests\FormInteractiveUpdate;
use App\Models\FormInteractive;
use App\Models\FormInteractiveResponse;
use App\Services\GoogleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Production\Jobs\NewProjectJob;
use Pusher\Pusher;

class TestingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function detailForm(string $uid)
    {
        try {
            $data = FormInteractive::with('responses')
                ->where('uid', $uid)->first();

            $data->background = $data->background ? asset('storage/forms/' . $data->background) : null;
            $data->qrcode = asset('storage/' . $data->qrcode);

            $forms = json_decode($data->forms, true);
            $data->forms = collect($forms)->map(function ($item) {
                return [
                    'name' => $item['name'],
                    'id' => strtolower(preg_replace('/\s+/', '', $item['name'])),
                ];
            })->toArray();

            $data->response_detail = [];
            if (count($data->responses) > 0) {
                $outputResponse = [];
                foreach ($data->responses as $response) {
                    $outputResponse[] = [
                        'message' => json_decode($response->response, true),
                    ];
                }

                $data->response_detail = $outputResponse;
            }

            return apiResponse(
                generalResponse(
                    'success',
                    false,
                    $data->toArray()
                )
            );
        } catch (\Throwable $th) {
            return apiResponse(
                errorResponse($th)
            );
        }
    }

    public function updateForm(FormInteractiveUpdate $request, string $id)
    {
        $tmp = null;

        try {
            $data = $request->validated();

            $payload = [
                'name' => $data['name'],
                'forms' => json_encode($data['forms'])
            ];

            if (
                (isset($data['image'])) &&
                ($data['image'])
            ) {
                $tmp = uploadImageandCompress("forms", 10, $data['image']);
                $payload['background'] = $tmp;
            }

            $currentData = FormInteractive::select('background')->where('uid', $id)->first();

            $form = FormInteractive::where('uid', $id)
                ->update($payload);

            if (
                ($form) &&
                $tmp &&
                (
                    ($currentData) &&
                    ($currentData->background) &&
                    is_file(storage_path('app/public/forms/' . $currentData->background))
                )
            ) {
                unlink(storage_path('app/public/forms/' . $currentData->background));
            }

            return apiResponse(
                generalResponse(
                    'Success update interactive form',
                    false,
                )
            );
        } catch (\Throwable $th) {
            if ($tmp) {
                deleteImage($tmp);
            }

            return apiResponse(
                errorResponse($th)
            );
        }
    }

    public function deleteForm(string $uid)
    {
        try {
            $data = FormInteractive::select('*')->where('uid', $uid)->first();

            if (
                ($data->background) &&
                (is_file(storage_path('app/public/forms/' . $data->background)))
            ) {
                unlink(storage_path('app/public/forms/' . $data->background));
            }

            if (is_file(storage_path('app/public/' . $data->qrcode))) {
                unlink(storage_path('app/public/' . $data->qrcode));
            }

            $data->delete();

            return apiResponse(
                generalResponse(
                    'Form is deleted',
                    false,
                )
            );
        } catch (\Throwable $th) {
            return apiResponse(
                errorResponse($th)
            );
        }
    }

    public function storeFormResponse(Request $request, string $uid)
    {
        try {
            $all = $request->all();

            FormInteractiveResponse::create([
                'form_interactive_id' => getIdFromUid($uid, new FormInteractive()),
                'response' => json_encode($all)
            ]);

            return apiResponse(
                generalResponse(
                    'success',
                    false,
                )
            );
        } catch (\Throwable $th) {
            return apiResponse(
                errorResponse($th)
            );
        }
    }

    public function storeForm(FormInteractiveRequest $request)
    {
        $tmp = null;

        try {
            $data = $request->validated();

            if (
                (isset($data['image'])) &&
                ($data['image'])
            ) {
                $tmp = uploadImageandCompress("forms", 10, $data['image']);
            }

            $form = FormInteractive::create([
                'name' => $data['name'],
                'forms' => json_encode($data['forms']),
                'background' => $tmp,
            ]);

            $qrcodeData = config('app.frontend_url') . '/forms/d/' . $form->uid;
            $qrcode = generateQrcode($qrcodeData, 'qrcode-' . preg_replace('/\s+/', '', $form->name) . '.png');

            if (!$qrcode) {
                throw new GenerateQrcodeError();
            }

            FormInteractive::where('id', $form->id)
                ->update(['qrcode' => $qrcode]);

            return apiResponse(
                generalResponse(
                    'Success create new interactive form',
                    false,
                )
            );
        } catch (\Throwable $th) {
            if ($tmp) {
                deleteImage($tmp);
            }

            return apiResponse(
                errorResponse($th)
            );
        }
    }

    /**
     * @return array
     */
    public function forms()
    {
        $forms = FormInteractive::with('responses')->get();

        $output = [];
        foreach ($forms as $form) {
            $fields = json_decode($form->forms, true);

            $output[] = [
                'qrcode' => asset('storage/' . $form->qrcode),
                'forms' => $fields,
                'name' => $form->name,
                'id' => $form->uid,
                'field_count' => count($fields),
                'number_of_response' => count($form->responses),
            ];
        }

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    protected function getHeaderFormat()
    {
        return [
            'employee_id' => 2,
            'name' => 4,
            'nickname' => 5,
            'company_name' => 6,
            'position_name' => 7,
            'level_staff' => 8,
            'status' => 9,
            'join_date' => 10,
            'start_review_probation_date' => 12,
            'probation_status' => 13,
            'end_probation_date' => 14,
            'gender' => 21,
            'phone' => 22,
            'email' => 23,
            'education' => 24,
            'education_name' => 25,
            'education_major' => 26,
            'education_year' => 27,
            'id_number' => 28,
            ''
        ];
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

    public function manualAssignPM(Request $request)
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\ManualProjectImport, $request->file('excel'));

            $mainData = $data[0];

            $dateKey = 2;
            $nameKey = 3;
            $ledKey = 11;
            $picKey = 9;

            foreach ($mainData as $key => $project) {
                if ($key != 0) {
                    if ($project[$ledKey] != null && $project[$nameKey] != null && $project[$dateKey] != null) {
                        $picString = $project[$picKey];

                        $exp = explode(',', $picString);

                        $picList = [];
                        foreach ($exp as $pic) {
                            switch (strtolower($pic)) {
                                case 'wesley':
                                    $picEmail = 'wesleywiyadi@gmail.com';
                                    break;

                                case 'thalia':
                                    $picEmail = 'thaliaemon@gmail.com';
                                    break;

                                case 'nando':
                                    $picEmail = 'attahashinando@gmail.com';
                                    break;

                                case 'edwin':
                                    $picEmail = 'edwin.chan92@gmail.com';
                                    break;

                                case 'rudhi':
                                    $picEmail = 'rudhisoe@gmail.com';
                                    break;

                                default:
                                    $picEmail = 'wesleywiyadi@gmail.com';
                                    break;
                            }

                            $employee = \Modules\Hrd\Models\Employee::select('id')
                                ->where('email', $picEmail)
                                ->first();

                            $picList[] = [
                                'pic_id' => $employee->id,
                            ];
                        }

                        $projectName = strtolower($project[$nameKey]);
                        $projectDetail = \Modules\Production\Models\Project::whereRaw("lower(name) = '{$projectName}'")->first();

                        $projectDetail->personInCharges()->createMany($picList);
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return apiResponse(
                generalResponse(
                    'Success assign PIC',
                    false,
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return apiResponse(
                errorResponse($e)
            );
        }
    }

    public function manualAssignStatus(Request $request)
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\ManualProjectImport, $request->file('excel'));

            $mainData = $data[0];

            $dateKey = 2;
            $nameKey = 3;
            $ledKey = 11;
            $statusKey = 13;

            foreach ($mainData as $key => $project) {
                if ($key != 0) {
                    if ($project[$ledKey] != null && $project[$nameKey] != null && $project[$dateKey] != null) {
                        $status = strtolower($project[$statusKey]);
                        switch ($status) {
                            case 'done':
                                $statusData = \App\Enums\Production\ProjectStatus::Completed->value;
                                break;

                            case 'on going':
                                $statusData = \App\Enums\Production\ProjectStatus::OnGoing->value;
                                break;

                            case 'ready':
                                $statusData = \App\Enums\Production\ProjectStatus::ReadyToGo->value;
                                break;

                            default:
                                $statusData = null;
                                break;
                        }

                        $projectName = strtolower($project[$nameKey]);
                        $projectDetail = \Modules\Production\Models\Project::whereRaw("lower(name) = '{$projectName}'")->update(['status' => $statusData]);
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return apiResponse(
                generalResponse(
                    'Success assign status',
                    false,
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return apiResponse(
                errorResponse($e)
            );
        }
    }

    public function deleteCurrentProjects()
    {
        try {
            $projectService = new \Modules\Production\Services\ProjectService();

            // delete current project
            $projects = \Modules\Production\Models\Project::select('uid')->get();
            $projectUids = collect((object)$projects)->pluck('uid')->toArray();
            $projectService->bulkDelete($projectUids);

            return apiResponse(
                generalResponse(
                    'Delete success',
                    false
                )
            );
        } catch (\Throwable $e) {
            return apiResponse(errorResponse($e));
        }
    }

    public function manualMigrateProjects(Request $request)
    {
        $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\ManualProjectImport, $request->file('excel'));

        $mainData = $data[0];

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

        foreach ($mainData as $key => $project) {
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

        foreach ($output as $project) {
            $store = $projectService->store($project);

            if ($store['error']) {
                logging('error', ['name' => $project['name'], 'error' => $store]);
            }
        }

        return apiResponse(
            generalResponse(
                'Success migrate project',
                false
            )
        );
    }

    public function generateOfficialEmail()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\OfficialEmailList, 'email_list.xlsx');
    }

    public function spreadsheet()
    {
        $service = new GoogleService();

        $data = $service->spreadSheet('1Rrp_0srULfoeWLlxgElMTjkxPaJMFSD6XBU_JQL0_jI');

        $headers = $data[1];

        $body = array_splice($data, 2);

        $output = [];
        $format = $this->getHeaderFormat();
        foreach ($body as $key => $value) {
            $position = \Modules\Company\Models\Position::select('id')
                ->where('name', $value[$format['position_name']])
                ->first();

            $output[] = [
                'employee_id' => $value[$format['employee_id']],
                'name' => $value[$format['name']],
                'nickname' => $value[$format['nickname']],
                'company_name' => $value[$format['company_name']],
                'position_name' => $value[$format['position_name']],
                'position_id' => $position ? $position->id : 0,
                'level_staff' => $value[$format['level_staff']],
                'status' => $value[$format['status']],
                'join_date' => $value[$format['join_date']] ? date('Y-m-d', strtotime($value[$format['join_date']])) : $value[$format['join_date']],
                'start_review_probation_date' => isset($value[$format['start_review_probation_date']]) && ($value[$format['start_review_probation_date']]) ? date('Y-m-d', strtotime($value[$format['start_review_probation_date']])) : '',
                'probation_status' => $value[$format['probation_status']] ?? \App\Enums\Employee\ProbationStatus::Lulus->value,
                'end_probation_date' => isset($value[$format['end_probation_date']]) && ($value[$format['end_probation_date']]) ? date('Y-m-d', strtotime($value[$format['end_probation_date']])) : '',
                'gender' => $value[$format['gender']] ?? \App\Enums\Employee\Gender::Male->value,
                'phone' => $value[$format['phone']] ?? 0,
                'email' => $value[$format['email']] ?? '',
                'education' => $value[$format['education']] ?? '',
                'education_name' => $value[$format['education_name']] ?? '',
                'education_major' => $value[$format['education_major']] ?? '',
                'education_year' => $value[$format['education_year']] ?? '',
                'id_number' => $value[$format['id_number']] ?? 0,
            ];
        }

        return response()->json([
            'headers' => $headers,
            'body' => $output,
            'raw' => $body,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
