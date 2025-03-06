<?php

namespace App\Http\Controllers;

use App\Enums\Employee\Status;
use App\Enums\Production\WorkType;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Repository\EmployeePointProjectDetailRepository;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeePointRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Models\Project;
use Modules\Production\Services\ProjectRepositoryGroup;

class LandingPageController extends Controller
{
    private $projectRepoGroup;

    private $employeePointService;

    private $reportService;

    public function __construct(
        ProjectRepositoryGroup $projectRepoGroup,
        EmployeePointService $employeePointService,
        PerformanceReportService $reportService
    )
    {
        $this->projectRepoGroup = $projectRepoGroup;

        $this->employeePointService = $employeePointService;

        $this->reportService = $reportService;
    }

    protected function folders()
    {
        return [
            "BRIEF",
            "ASSET_3D",
            "ASSET_FOOTAGE",
            "FINAL_RENDER",
            "ASSET_SEMENTARA",
            "PREVIEW",
            "SKETSA",
            "TC",
            "RAW",
            "AUDIO"
        ];
    }

    protected function minifyFolders()
    {
        return [
            "FINAL_RENDER",
            "PREVIEW",
            'RAW',
            'OLD'
        ];
    }

    protected function pregName(string $name)
    {
        return preg_replace('/[.,\"~@\/]/', '', $name);
    }

    public function index()
    {
        $customer = Project::latest()->first();
        $name = $this->pregName(name: $customer->name);
        $name = stringToPascalSnakeCase($name);

        $date = date('d', strtotime($customer->project_date));
        $month = date('m', strtotime($customer->project_date));
        $monthText = MonthInBahasa(date('m', strtotime($customer->project_date)));
        $subFolder1 = strtoupper($month . '_' . $monthText);
        $prefixName = strtoupper($date . "_" . $monthText);

        $subFolder2 = $prefixName . '_' . $name;

        $year = date('Y', strtotime($customer->project_date));

        $parent =  "/{$year}/{$subFolder1}/{$subFolder2}";

        $toBeCreatedParents = [];
        $toBeCreatedNames = [];
        foreach ($this->folders() as $folder) {
            $toBeCreatedParents[] = $parent;
            $toBeCreatedNames[] = $folder;
        }

        // set current path
        $currentPath = [];
        foreach ($toBeCreatedParents as $keyFolder => $folder) {
            $currentPath[] = $folder . "/" . $toBeCreatedNames[$keyFolder];
        }

        $sharedFolder = getSettingByKey('nas_current_root');

        return [
            'shared_folder' => $sharedFolder,
            'year' => $year,
            'month_name' => $subFolder1,
            'project_name' => $name,
            'prefix_project_name' => $prefixName,
            'child_folders' => $this->folders(),
            'project_id' => $customer->id,
        ];
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
