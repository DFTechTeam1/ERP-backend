<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Enums\Cache\CacheKey;
use App\Enums\Employee\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Hrd\Http\Requests\Employee\AddAsUser;
use Modules\Hrd\Http\Requests\Employee\Create;
use Modules\Hrd\Http\Requests\Employee\Update;
use Modules\Hrd\Http\Requests\Employee\UpdateBasicInfo;
use Modules\Hrd\Http\Requests\Employee\UpdateIdentity;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeeService;

class EmployeeController extends Controller
{
    private $employeeService;

    private $repo;

    public function __construct(
        EmployeeService $employeeService,
        EmployeeRepository $repo
    )
    {
        $this->employeeService = $employeeService;

        $this->repo = $repo;
    }
    /**
     * Get list of data
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        $selects = [
            'id', 'uid', 'name',
            'address',
            'employee_id',
            'nickname',
            'branch_id',
            'position_id',
            'level_staff',
            'status',
            'join_date',
            'end_date',
            'email',
            'date_of_birth',
            'place_of_birth',
            'phone',
            'religion',
            'gender',
            'martial_status',
            'user_id'
        ];

        return apiResponse(
            $this->employeeService->list(
                implode(',', $selects),
                '',
                [
                    'position:id,uid,name',
                    'user:id,uid,email,employee_id',
                    'branch:id,short_name',
                    'jobLevel:id,name'
                ]
            )
        );
    }

    /**
     * Get list of 3D modeller Employee
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function get3DModeller(string $projectUid, string $taskUid): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->employeeService->get3DModeller($projectUid, $taskUid));
    }

    public function generateRandomPassword(): \Illuminate\Http\JsonResponse
    {
        return apiResponse(generalResponse(
            message: 'Success',
            error: false,
            data: [
                'password' => generateRandomPassword(14),
            ]
        ));
    }

    /**
     * Get all available status from enums
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStatus(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->employeeService->getAllStatus());
    }

    public function submitImport(Request $request)
    {
        return apiResponse($this->employeeService->submitImport($request->toArray()));
    }

    public function import(Request $request)
    {
        return apiResponse($this->employeeService->import($request->file('excel')));
        // return apiResponse($this->employeeService->import('static_file/employee.xlsx'));
    }

    public function getVJ(string $projectUid)
    {
        return apiResponse($this->employeeService->getVJ($projectUid));
    }

    public function downloadTemplate()
    {
        return $this->employeeService->downloadTemplate();
    }

    /**
     * Function to generate new Employee ID (For new user only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateEmployeeID()
    {
        return apiResponse($this->employeeService->generateEmployeeID());
    }

    /**
     * Validate employee ID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateEmployeeID(Request $request): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->employeeService->validateEmployeeID($request->toArray()));
    }

    /**
     * Function to get all employees data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll()
    {
        return apiResponse($this->employeeService->getAll());
    }

    /**
     * Function to check email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmail()
    {
        return apiResponse($this->employeeService->checkFieldsUnique('email', request('email')));
    }

    /**
     * Function to check id number
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIdNumber()
    {
        return apiResponse($this->employeeService->checkFieldsUnique('id_number', request('id_number')));
    }

    /**
     * Get specific data by uid
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $uid)
    {
        return apiResponse($this->employeeService->show($uid));
    }

    /**
     * Function to assign employee to webapp user
     *
     * @param AddAsUser $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAsUser(AddAsUser $request)
    {
        return apiResponse($this->employeeService->addAsUser($request->validated()));
    }

    /**
     * Get project manaagers only
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectManagers()
    {
        return apiResponse($this->employeeService->getProjectManagers());
    }

    public function activateAccount(string $key)
    {
        return apiResponse($this->employeeService->activateAccount($key));
    }

    /**
     * Create new data
     * @param Create $request
     * @return void
     */
    public function store(Create $request)
    {
        $data = $request->validated();

        return apiResponse($this->employeeService->store($data));
    }

    /**
     * Update selected data
     * @param Update $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Update $request, string $uid)
    {
        $data = $request->validated();

        return apiResponse($this->employeeService->update($data, $uid));
    }

    /**
     * Update selected data
     * @param Update $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBasicInfo(UpdateBasicInfo $request, string $uid)
    {
        return apiResponse($this->employeeService->updateBasicInfo($request->validated(), $uid));
    }

    /**
     * Update selected data
     * @param Update $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateIdentity(UpdateIdentity $request, string $uid)
    {
        return apiResponse($this->employeeService->updateIdentity($request->validated(), $uid));
    }

    /**
     * Delete specific data
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($uid)
    {
        return apiResponse($this->employeeService->delete($uid));
    }

    /**
     * Delete multiple data
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        return apiResponse($this->employeeService->bulkDelete(
            $request->uids
        ));
    }

    /**
     * Store employee family member
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFamily(\Modules\Hrd\Http\Requests\Employee\Family $request, string $employeeUid)
    {
        return apiResponse($this->employeeService->storeFamily($request->validated(), $employeeUid));
    }

    /**
     * Update employee family member
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFamily(\Modules\Hrd\Http\Requests\Employee\Family $request, string $familyUid)
    {
        return apiResponse($this->employeeService->updateFamily($request->validated(), $familyUid));
    }

    /**
     * Get family of each employee
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initFamily(string $employeeUid)
    {
        return apiResponse($this->employeeService->initFamily($employeeUid));
    }

    /**
     * Export employees with some conditions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request)
    {
        return apiResponse($this->employeeService->export($request->all()));
    }

    /**
     * Get family of each employee
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFamily(string $familyUid)
    {
        return apiResponse($this->employeeService->deleteFamily($familyUid));
    }

    /**
     * Get emergency contact of each employee
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initEmergency(string $employeeUid)
    {
        return apiResponse($this->employeeService->initEmergency($employeeUid));
    }

    /**
     * Store employee family member
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeEmergency(\Modules\Hrd\Http\Requests\Employee\EmergencyContact $request, string $employeeUid)
    {
        return apiResponse($this->employeeService->storeEmergency($request->validated(), $employeeUid));
    }

    /**
     * Update employee emergency contact
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEmergency(\Modules\Hrd\Http\Requests\Employee\EmergencyContact $request, string $familyUid)
    {
        return apiResponse($this->employeeService->updateEmergency($request->validated(), $familyUid));
    }

    /**
     * delete emergency contact
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteEmergency(string $emergencyUid)
    {
        return apiResponse($this->employeeService->deleteEmergency($emergencyUid));
    }

    /**
     * update employment data
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEmployment(\Modules\Hrd\Http\Requests\Employee\UpdateEmployment $request, string $employeeUid)
    {
        return apiResponse($this->employeeService->updateEmployment($request->validated(), $employeeUid));
    }

    public function resign(\Modules\Hrd\Http\Requests\Employee\Resign $request, string $employeeUid)
    {
        return apiResponse($this->employeeService->resign($request->validated(), $employeeUid));
    }

    /**
     * Get employment chart options for frontend
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmploymentChart(): \Illuminate\Http\JsonResponse
    {
        $employees = $this->repo->list(
            select: "id,name,nickname,status,join_date",
            where: "deleted_at IS NULL"
        );

        return apiResponse($this->employeeService->getEmploymentChart($employees));
    }

    /**
     * Get all element for dashboard chart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardElement(): \Illuminate\Http\JsonResponse
    {
        $employees = Cache::get(CacheKey::HrDashboardEmoloyeeList->value);
        if (!$employees) {
            $employees = Cache::rememberForever(CacheKey::HrDashboardEmoloyeeList->value, function () {
                return $this->repo->list(
                    select: "id,name,nickname,status,join_date,gender,job_level_id,date_of_birth",
                    where: "deleted_at IS NULL AND status NOT IN (" . Status::Deleted->value . "," . Status::Inactive->value . ") AND end_date IS NULL"
                );
            });
        }

        return apiResponse(
            generalResponse(
                message: "Success",
                data: [
                    'employmentStatus' => $this->employeeService->getEmploymentChart($employees)['data'],
                    'lengthOfService' => $this->employeeService->getLengthOfServiceChart(employees: $employees)['data'],
                    'activeStaff' => $this->employeeService->getActiveStaffChart()['data'],
                    'genderDiversity' => $this->employeeService->getGenderDiversityChart(employees: $employees)['data'],
                    'jobLevel' => $this->employeeService->getJobLevelChart(employees: $employees)['data'],
                    'ageAverage' => $this->employeeService->getAgeAverageChart(employees: $employees)['data']
                ]
            )
        );
    }
}
