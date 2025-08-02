<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    public function getCountries()
    {
        $data = \Illuminate\Support\Facades\DB::table('countries')
            ->selectRaw('id as value,name as title')
            ->get();

        return apiResponse(
            generalResponse(
                'success',
                false,
                $data->toArray(),
            )
        );
    }

    public function getStates()
    {
        $code = request('country_id');

        $data = \Illuminate\Support\Facades\DB::table('states')
            ->selectRaw('id as value,name as title')
            ->where('country_id', $code)
            ->get();

        return apiResponse(
            generalResponse(
                'success',
                false,
                $data->toArray(),
            )
        );
    }

    public function getCities()
    {
        $code = request('state_id');

        $data = \Illuminate\Support\Facades\DB::table('cities')
            ->selectRaw('id as value,name as title')
            ->where('state_id', $code)
            ->get();

        return apiResponse(
            generalResponse(
                'success',
                false,
                $data->toArray(),
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
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
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}
