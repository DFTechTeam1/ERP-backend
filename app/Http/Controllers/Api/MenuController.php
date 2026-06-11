<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new MenuService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->getMenus());
    }

    /**
     * Menu tree for the access-token-authenticated user, built fresh from the
     * user's current permissions. Kept out of the JWT so the token stays small
     * and the menu always reflects the latest permission/role changes.
     */
    public function userMenu(Request $request)
    {
        $user = $request->user();

        $menus = $this->service->getNewFormattedMenu(
            $user->getAllPermissions()->toArray(),
            $user->roles->toArray(),
        );

        return apiResponse(
            generalResponse(
                'Success',
                false,
                ['menus' => $menus],
            ),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
