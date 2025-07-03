<?php

namespace App\Http\Controllers;

use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Services\ProjectRepositoryGroup;

class LandingPageController extends Controller
{
    private $projectRepoGroup;

    private $employeePointService;

    private $reportService;

    private $employeeRepo;

    public function __construct(
        ProjectRepositoryGroup $projectRepoGroup,
        EmployeePointService $employeePointService,
        PerformanceReportService $reportService,
        EmployeeRepository $employeeRepo
    ) {
        $this->projectRepoGroup = $projectRepoGroup;

        $this->employeePointService = $employeePointService;

        $this->reportService = $reportService;

        $this->employeeRepo = $employeeRepo;
    }

    protected function getProjectData()
    {
        try {
            // code...
            $data = \Modules\Production\Models\Project::selectRaw('id,name,project_date,status')
                ->with([
                    'personInCharges:id,pic_id,project_id',
                ])
                ->get();

            $output = [];

            $data = \Illuminate\Support\Facades\DB::table('projects')
                ->leftJoin(table: 'project_classes', first: 'project_classes.project_id', operator: '=', second: 'projects.id')
                ->whereDate('projects.project_date', '>=', '2025-01-1')
                ->groupBy('p.project_class_id')
                ->dumpRawSql();
        } catch (\Throwable $th) {
            // throw $th;
            return errorResponse($th);
        }
    }

    public function index()
    {
        return view('landing');
    }

    public function sendToNAS()
    {
        $filePath = public_path('images/user.png');
        $username = 'ilhamgumilang'; // Change this to NAS username
        $password = 'Ilham..123'; // Change this to NAS password

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://192.168.100.105:3500',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($filePath),
            ],
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            throw new \Exception('Upload failed: '.curl_error($curl));
        }

        curl_close($curl);

        echo json_encode($response);
    }
}
