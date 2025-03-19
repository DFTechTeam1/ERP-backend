<?php

namespace App\Http\Controllers;

use App\Enums\Employee\Religion;
use App\Enums\Employee\Status;
use App\Enums\Production\WorkType;
use App\Exports\PrepareEmployeeMigration;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Company\Models\Position;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeePointProjectDetailRepository;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeePointRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Hrd\Services\TalentaService;
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
    )
    {
        $this->projectRepoGroup = $projectRepoGroup;

        $this->employeePointService = $employeePointService;

        $this->reportService = $reportService;

        $this->employeeRepo = $employeeRepo;
    }

    public function index()
    {
        $employees = $this->employeeRepo->list(
            select: 'id,email,talenta_user_id',
            where: "deleted_at IS NULL AND talenta_user_id IS NULL",
            limit: 1
        );

        $talentaService = new TalentaService();
        $talentaService->setUrl(type: "all_employee");
        foreach ($employees as $employee) {
            $talentaService->setUrlParams(['email' => $employee->email]);
            return $talentaService->makeRequest();
        }

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
