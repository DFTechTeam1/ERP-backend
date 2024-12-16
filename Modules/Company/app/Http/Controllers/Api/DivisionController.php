<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Company\Http\Requests\DivisionCreateRequest;
use Modules\Company\Http\Requests\DivisionUpdateRequest;
use Modules\Company\Models\Division;
use Modules\Company\Services\DivisionService;

class DivisionController extends Controller
{
    private DivisionService $divisionService;

    /**
     * @param DivisionService $divisionService
     */
    public function __construct(DivisionService $divisionService)
    {
        $this->divisionService = $divisionService;
    }


    /**
     * Get list of data
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        return apiResponse($this->divisionService->list('uid,name,parent_id','',['parentDivision:id,uid,name']));
    }

    public function allDivisions()
    {
        return apiResponse($this->divisionService->allDivisions());
    }

    /**
     * Get specific data by uid
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $uid)
    {
        return apiResponse($this->divisionService
            ->show($uid, 'id,uid,name,parent_id', ['parentDivision:id,uid,name','childDivisions:id,parent_id,uid,name','positions:division_id,uid,name']));
    }

    /**
     * Create new data
     *
     * @param DivisionCreateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(DivisionCreateRequest $request)
    {
        $data = $request->validated();

        return apiResponse($this->divisionService->store($data));
    }


    /**
     * @param DivisionUpdateRequest $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(DivisionUpdateRequest $request, string $uid)
    {
        $data = $request->validated();

        return apiResponse($this->divisionService->update($data, $uid));
    }


    /**
     * Delete specific data
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(string $uid)
    {
        return apiResponse($this->divisionService->delete($uid));
    }


    /**
     * Delete multiple data
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $uids = $request->input('ids');

        return apiResponse($this->divisionService->bulkDelete($uids, 'uid'));
    }
}
