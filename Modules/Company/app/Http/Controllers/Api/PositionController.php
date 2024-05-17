<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Company\Http\Requests\PositionCreateRequest;
use Modules\Company\Http\Requests\PositionUpdateRequest;
use Modules\Company\Models\Position;
use Modules\Company\Services\PositionService;

class PositionController extends Controller
{
    private PositionService $positionService;

    /**
     * @param PositionService $positionService
     */
    public function __construct(PositionService $positionService)
    {
        $this->positionService = $positionService;
    }


    /**
     * Get list of data
     */
    public function list()
    {
        $data = $this->positionService->list('uid,name,division_id','',['division:id,name,uid']);
        return apiResponse($data);
    }

    public function getAll()
    {
        return apiResponse($this->positionService->getAll());
    }

    /**
     * Get specific data by id
     */
    public function show(string $uid)
    {
        $data = $this->positionService->show($uid,'id,uid,name,division_id', [
            'division:id,name,uid',
            'employees:position_id,uid,name',
            // 'jobs:position_id,uid,name'
        ]);
        return apiResponse($data);
    }

    /**
     * Store data
     */
    public function store(PositionCreateRequest $request)
    {
        $data = $request->validated();

        return apiResponse($this->positionService->store($data));
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param integer $id
     * @param string $where
     *
     * @return array
     */
    public function update(PositionUpdateRequest $request, string $uid)
    {
        $data = $request->validated();

        return apiResponse($this->positionService->update($data, $uid));
    }

    /**
     * Delete selected data
     */
    public function delete(string $uid)
    {
        return apiResponse($this->positionService->delete($uid));
    }

    /**
     * Delete bulk data
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->input('uids');

        return $this->positionService->bulkDelete($data, 'uid');
    }
}
