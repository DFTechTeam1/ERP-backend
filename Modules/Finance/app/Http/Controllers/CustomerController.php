<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Project\Deal\UpdateCustomer;
use Modules\Production\Http\Requests\Project\Deals\Customer\StoreCustomer;
use Modules\Production\Services\CustomerService;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service
    )
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(
            relation: [
                'invoices:id,customer_id,project_deal_id,amount',
            ]
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomer $request)
    {
        return apiResponse(
            $this->service->store($request->validated())
        );
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomer $request, $id)
    {
        return apiResponse(
            $this->service->update(
                $request->validated(),
                $id,
            )
        );
    }

    /**
     * Remove the specified resource from storage.
     * @param mixed $id
     * 
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        return apiResponse(
            $this->service->delete($id)
        );
    }
}
