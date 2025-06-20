<?php

namespace Modules\Addon\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Addon\Http\Requests\Addon\AskDeveloper;
use Modules\Addon\Http\Requests\Addon\Create;
use Modules\Addon\Http\Requests\Addon\Upgrade;
use Modules\Addon\Services\AddonService;

class AddonController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new AddonService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list('id as uid,name,description,preview_img'));
    }

    public function getUpdatedAddons()
    {
        return apiResponse($this->service->getUpdatedAddons());
    }

    /**
     * Get All Addons without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll()
    {
        return apiResponse($this->service->getAll());
    }

    public function upgrades(Upgrade $request, $id)
    {
        return apiResponse($this->service->upgrades($request->validated(), (int) $id));
    }

    public function askDeveloper(AskDeveloper $request)
    {
        return apiResponse($this->service->askDeveloper($request->validated()));
    }

    public function validate()
    {
        $response = $this->service->validateConfiguration();

        return apiResponse($response);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('addon::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return apiResponse($this->service->show((int) $id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('addon::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    public function download($id, $type)
    {
        return apiResponse($this->service->download((int) $id, $type));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray(),
        ));
    }
}
