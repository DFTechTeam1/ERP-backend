<?php

namespace App\Http\Controllers;

use App\Actions\Project\DetailProject;
use App\Actions\Project\GetProjectStatistic;
use App\Enums\Employee\Religion;

use App\Enums\Employee\Status;
use App\Enums\Production\WorkType;
use App\Exports\NewTemplatePerformanceReportExport;
use App\Exports\PrepareEmployeeMigration;
use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
use Modules\Production\Services\ProjectRepositoryGroup;
use \Illuminate\Support\Str;

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
        $data = $this->employeePointService->renderEachEmployeePoint(28, '2024-12-01', '2024-12-31');
        return $data;
        // $employees = array(
        //     array('id' => '1','name' => 'Wesley Wiyadi','position_id' => '1'),
        //     array('id' => '2','name' => 'Edwin Chandra Wijaya Ngo','position_id' => '2'),
        //     array('id' => '3','name' => 'Rudhi Soegiarto','position_id' => '3'),
        //     array('id' => '4','name' => 'Hutomo Putra Winata','position_id' => '19'),
        //     array('id' => '5','name' => 'Raja Safrizal Arnindo Attahashi','position_id' => '3'),
        //     array('id' => '6','name' => 'Angelina Sigit','position_id' => '5'),
        //     array('id' => '7','name' => 'David Firdaus','position_id' => '5'),
        //     array('id' => '8','name' => 'Riyadus Solihin','position_id' => '6'),
        //     array('id' => '9','name' => 'Sucha Aji Nugroho','position_id' => '4'),
        //     array('id' => '10','name' => 'Giantoro Susilo','position_id' => '6'),
        //     array('id' => '11','name' => 'Gilang Rizky Al Mizan','position_id' => '4'),
        //     array('id' => '12','name' => 'Rani Claudia Bitjoli','position_id' => '4'),
        //     array('id' => '13','name' => 'Tedi Trihardi','position_id' => '7'),
        //     array('id' => '14','name' => 'Thalia Miranda Soedarmadji','position_id' => '3'),
        //     array('id' => '15','name' => 'Muhammad Kanza Eka Ghifari','position_id' => '5'),
        //     array('id' => '16','name' => 'Hafid Asari','position_id' => '4'),
        //     array('id' => '17','name' => 'Ilyasa Octavianto','position_id' => '4'),
        //     array('id' => '18','name' => 'Edward Suryapto','position_id' => '5'),
        //     array('id' => '19','name' => 'Muhamad Nurisya','position_id' => '4'),
        //     array('id' => '20','name' => 'Fuad Ashari','position_id' => '5'),
        //     array('id' => '21','name' => 'Thoriq Nur Hidayah','position_id' => '8'),
        //     array('id' => '22','name' => 'Dinda Nurvianti Partiwi','position_id' => '9'),
        //     array('id' => '23','name' => 'Devika Tanuwidjaja','position_id' => '8'),
        //     array('id' => '24','name' => 'Nehemia Lantis Jojo Winarjati','position_id' => '3'),
        //     array('id' => '25','name' => 'Galih Ayu Indah Triani','position_id' => '10'),
        //     array('id' => '26','name' => 'Gabriella Marcelina Sunartho','position_id' => '11'),
        //     array('id' => '27','name' => 'Yoga Pratama Abdi Margo','position_id' => '4'),
        //     array('id' => '28','name' => 'Isyfi Arief Darmawan','position_id' => '7'),
        //     array('id' => '29','name' => 'Muhammad Iqbal Jitno Hassan','position_id' => '12'),
        //     array('id' => '30','name' => 'Fuad Azaim Siraj','position_id' => '7'),
        //     array('id' => '31','name' => 'Reza Pratama Koestijanto','position_id' => '7'),
        //     array('id' => '32','name' => 'Nyoman Ariyo Pradana','position_id' => '12'),
        //     array('id' => '33','name' => 'Ariya Putra Sundava','position_id' => '12'),
        //     array('id' => '34','name' => 'Sherlynn Yuwono','position_id' => '8'),
        //     array('id' => '35','name' => 'Eza Muhammad Shofi','position_id' => '4'),
        //     array('id' => '36','name' => 'Pieter','position_id' => '13'),
        //     array('id' => '37','name' => 'Charles Eduardo','position_id' => '11'),
        //     array('id' => '38','name' => 'Vicky Apriyana Firdaus','position_id' => '6'),
        //     array('id' => '39','name' => 'Ferrel Timothy Sutanto','position_id' => '13'),
        //     array('id' => '40','name' => 'Dhio Pandji Soemardjo','position_id' => '12'),
        //     array('id' => '41','name' => 'Erik Wahyu Saputro','position_id' => '15'),
        //     array('id' => '42','name' => 'Nur Laily Ida Yagshya','position_id' => '4'),
        //     array('id' => '43','name' => 'Jeremy Fredrick Manasye ','position_id' => '4'),
        //     array('id' => '44','name' => 'Michelle Lie','position_id' => '4'),
        //     array('id' => '45','name' => 'Andini Safa Athalia','position_id' => '16'),
        //     array('id' => '46','name' => 'Dhea Milinia Sefira','position_id' => '17'),
        //     array('id' => '47','name' => 'Yanuar Andi Rahman','position_id' => '16'),
        //     array('id' => '48','name' => 'Indra Setya Himawan','position_id' => '7'),
        //     array('id' => '49','name' => 'Ilham Meru Gumilang','position_id' => '18'),
        //     array('id' => '50','name' => 'Mochammad Fachrizal Afandi','position_id' => '18'),
        //     array('id' => '51','name' => 'Rizki Agung Fatchurrahman','position_id' => '19'),
        //     array('id' => '52','name' => 'Danny Dwi Prasetya','position_id' => '7'),
        //     array('id' => '53','name' => 'Ridwan Gavyn Ramadhan','position_id' => '7'),
        //     array('id' => '54','name' => 'Arif Cendekiawan','position_id' => '7'),
        //     array('id' => '55','name' => 'Maximillian Serafino Suprapto','position_id' => '20'),
        //     array('id' => '56','name' => 'Bagas Prila Ardian','position_id' => '20'),
        //     array('id' => '57','name' => 'Ardito Kenanya Hudson Widiono','position_id' => '8'),
        //     array('id' => '58','name' => 'Fadhil Indiko Putra','position_id' => '19'),
        //     array('id' => '59','name' => 'Aurellyn Briza','position_id' => '7'),
        //     array('id' => '60','name' => 'Rahmad Firdaus','position_id' => '7'),
        //     array('id' => '61','name' => 'Ardian Firmansyah','position_id' => '5'),
        //     array('id' => '62','name' => 'Avief Reja Satria','position_id' => '3'),
        //     array('id' => '63','name' => 'Fajar Ramadhan','position_id' => '7'),
        //     array('id' => '64','name' => 'Muhammad Rizky Al Reza Syamsa Putra','position_id' => '7'),
        //     array('id' => '65','name' => 'Noval Oktafian','position_id' => '7'),
        //     array('id' => '66','name' => 'Yumna Syarifah','position_id' => '4')
        // );
        // foreach ($employees as $employee) {
        //     Employee::where('id', $employee['id'])
        //         ->update(['position_id' => $employee['position_id']]);
        // }
        // return Excel::download(new PrepareEmployeeMigration, 'employee_updated.xlsx');
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
