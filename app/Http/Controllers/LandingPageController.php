<?php

namespace App\Http\Controllers;

use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;
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

    public function index()
    {
        // $image = 'https://data-center.dfactory.pro/dfactory.png';
        // $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('quotation.quotation', compact('image'));
        // return $pdf->download('quotation.pdf');

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
