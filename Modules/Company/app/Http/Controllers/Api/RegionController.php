<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Company\Http\Requests\City\Create as CityCreate;
use Modules\Company\Http\Requests\City\Update as CityUpdate;
use Modules\Company\Http\Requests\Country\Create;
use Modules\Company\Http\Requests\Country\Update;
use Modules\Company\Http\Requests\State\Create as StateCreate;
use Modules\Company\Http\Requests\State\Update as StateUpdate;
use Modules\Company\Services\CityService;
use Modules\Company\Services\CountryService;
use Modules\Company\Services\StateService;

class RegionController extends Controller
{
    public function __construct(
        private readonly CountryService $countryService,
        private readonly StateService $stateService,
        private readonly CityService $cityService
    )
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Store a newly created country in storage.
     * @param Create $request
     * @return JsonResponse
     */
    public function storeCountry(Create $request): JsonResponse
    {
        return apiResponse($this->countryService->store($request->validated()));
    }

    /**
     * Update the specified country in storage.
     * @param Update $request
     * @param mixed $countryId
     * @return JsonResponse
     */
    public function updateCountry(Update $request, $countryId): JsonResponse
    {
        return apiResponse($this->countryService->update($request->validated(), $countryId));
    }

    /**
     * Delete the specified country from storage.
     * @param mixed $countryId
     * @return JsonResponse
     */
    public function deleteCountry($countryId): JsonResponse
    {
        return apiResponse($this->countryService->delete($countryId));
    }

    /**
     * Get paginated countries
     * @return JsonResponse
     */
    public function paginationCountries(): JsonResponse
    {
        return apiResponse($this->countryService->list());
    }

    /**
     * Get countries selection list
     * @return JsonResponse
     */
    public function requestCountriesSelectionList(): JsonResponse
    {
        return apiResponse($this->countryService->requestCountriesSelectionList());
    }

    /**
     * Get states selection list
     * @return JsonResponse
     */
    public function requestStatesSelectionList(): JsonResponse
    {
        return apiResponse($this->stateService->requestStatesSelectionList());
    }

    /**
     * Get all countries
     * @return JsonResponse
     */
    public function getCountries(): JsonResponse
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

    /**
     * Store a newly created state in storage.
     * @param StateCreate $request
     * @return JsonResponse
     */
    public function storeState(StateCreate $request): JsonResponse
    {
        return apiResponse($this->stateService->store($request->validated()));
    }

    /**
     * Update the specified state in storage.
     * @param StateUpdate $request
     * @param mixed $stateId
     * @return JsonResponse
     */
    public function updateState(StateUpdate $request, $stateId): JsonResponse
    {
        return apiResponse($this->stateService->update($request->validated(), $stateId));
    }

    /**
     * Delete the specified state from storage.
     * @param mixed $stateId
     * @return JsonResponse
     */
    public function deleteState($stateId): JsonResponse
    {
        return apiResponse($this->stateService->delete($stateId));
    }

    /**
     * Get paginated states
     * @return JsonResponse
     */
    public function paginationStates(): JsonResponse
    {
        return apiResponse($this->stateService->list(
            select: '*',
            relation: [
                'country:id,name,iso3',
            ]
        ));
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

    /**
     * Store a newly created city in storage.
     * @param CityCreate $request
     * @return JsonResponse
     */
    public function storeCity(CityCreate $request): JsonResponse
    {
        return apiResponse($this->cityService->store($request->validated()));
    }

    /**
     * Update the specified city in storage.
     * @param CityUpdate $request
     * @param mixed $cityId
     * @return JsonResponse
     */
    public function updateCity(CityUpdate $request, $cityId): JsonResponse
    {
        return apiResponse($this->cityService->update($request->validated(), $cityId));
    }

    /**
     * Delete the specified city from storage.
     * @param mixed $cityId
     * @return JsonResponse
     */
    public function deleteCity($cityId): JsonResponse
    {
        return apiResponse($this->cityService->delete($cityId));
    }

    /**
     * Get paginated cities
     * @return JsonResponse
     */
    public function paginationCities(): JsonResponse
    {
        return apiResponse($this->cityService->list(
            select: '*',
            relation: [
                'state:id,name,country_id',
                'state.country:id,name,iso3',
            ]
        ));
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
