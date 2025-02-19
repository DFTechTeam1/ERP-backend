<?php

namespace App\Http\Controllers;

use App\Enums\Production\WorkType;
use App\Enums\System\BaseRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\EmployeeTaskPoint;
use Modules\Hrd\Services\TalentaService;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTaskPicHistory;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Services\ProjectRepositoryGroup;
use Modules\Production\Services\ProjectService;

class LandingPageController extends Controller
{
    private $projectRepoGroup;

    public function __construct(
        ProjectRepositoryGroup $projectRepoGroup
    )
    {
        $this->projectRepoGroup = $projectRepoGroup;
    }

    public function migrateData()
    {
        $data = DB::table('project_task_pic_logs as l')
            ->selectRaw("DISTINCT l.project_task_id,l.employee_id,l.work_type,t.project_id,t.name as task_name,p.name as project_name,tp.point,tp.additional_point")
            ->join("project_tasks as t", "t.id", "=", "l.project_task_id")
            ->join("projects as p", "p.id", "t.project_id")
            ->join("employee_task_points as tp", function (JoinClause $join) {
                $join->on("tp.project_id", "=", "t.project_id")
                    ->on("tp.employee_id", "=", "l.employee_id");
            })
            ->whereRaw("l.work_type = '" . WorkType::Assigned->value . "'")
            ->get();

        // group by employee id then project id
        $groups = [];
        foreach ($data as $dataGroup) {
            $groups[$dataGroup->employee_id][] = $dataGroup;
        }

        $payload = [];

        foreach ($groups as $employeeId => $detailPoint) {
            foreach ($detailPoint as $point) {
                $payload[$employeeId][$point->project_id][] = $point;
            }
        }

        // reformat
        $format = [];
        // foreach ($payload as $employeeId => $dataPoint) {
        //     $format[] = [
        //         'empmloyee_id' => $employeeId,
        //         'total_point' => count($dataPoint[$projectId]) + $taskPoint[0]->additional_point,
        //         'additional_point' => $taskPoint[0]->additional_point,
        //         'type' => 'production'
        //     ];
        // }

        return [
            // 'format' => $format,
            'payload' => $payload
        ];
    }

    public function index()
    {
        return $this->migrateData();
        return view('landing');
    }

    public function sendToNAS()
    {
        $filePath = public_path('images/user.png');
        $username = "ilhamgumilang"; // Change this to NAS username
        $password = "Ilham..123"; // Change this to NAS password
    
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://192.168.100.105:3500',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($filePath)
            ],
        ]);
    
        $response = curl_exec($curl);
        if ($response === false) {
            throw new \Exception('Upload failed: ' . curl_error($curl));
        }
    
        curl_close($curl);

        echo json_encode($response);
    }
    
}
