<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Create;
use App\Http\Requests\User\Update;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    public function activate($key)
    {
        return apiResponse($this->service->activate($key));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return apiResponse($this->service->show($id));
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
        //
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
