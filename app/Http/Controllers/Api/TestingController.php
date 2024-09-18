<?php

namespace App\Http\Controllers\Api;

use App\Events\TestingEvent;
use App\Http\Controllers\Controller;
use App\Services\GoogleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

    public function testing(Request $request)
    {
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
