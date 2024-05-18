<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Hrd\Http\Requests\Employee\Create;
use Modules\Hrd\Http\Requests\Employee\Update;
use Modules\Hrd\Services\EmployeeService;

class EmployeeController extends Controller
{
    private EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }
    /**
     * Get list of data
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        return apiResponse(
            $this->employeeService->list(
                'id,uid,name,email,position_id,employee_id,level_staff,status,join_date,phone,placement,user_id',
                '',
                [
                    'position:id,uid,name',
                    'user:id,uid,email'
                ]
            )
        );
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
        return apiResponse($this->employeeService
            ->show($uid, '*'));
    }

    /**
     * Function to assign employee to webapp user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAsUser(Request $request)
    {
        return apiResponse($this->employeeService->addAsUser($request->user_id));
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
        // return apiResponse($this->employeeService->store($request->all()));
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
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
