<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\Create;
use App\Http\Requests\Roles\Update;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new RoleService();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
    }

    public function getAll()
    {
        return apiResponse($this->service->getAll());
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return apiResponse($this->service->show((int) $id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update $request, string $id)
    {
        return apiResponse($this->service->update($request->validated(), $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return apiResponse($this->service->destroy((int) $id));
    }

    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
