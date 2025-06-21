<?php

namespace Modules\Nas\Http\Controllers;

use App\Enums\ErrorCode\Code;
use App\Http\Controllers\Controller;
use App\Services\NasService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Nas\Http\Requests\Auth\Login;
use Modules\Nas\Http\Requests\TestConnection;

class NasController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new NasService;
    }

    public function login(Login $request)
    {
        $data = $request->validated();

        $data['api'] = 'SYNO.API.Auth';
        $data['format'] = 'cookie';
        $data['session'] = 'FileStation';
        $data['method'] = 'login';

        $response = Http::get(env('NAS_URL').'/auth.cgi', $data);

        $response = json_decode($response, true);

        $error = false;
        $code = Code::Success->value;
        $message = 'Success';

        if (
            ! isset($response['success']) ||
            (
                (isset($response['success'])) &&
                (! $response['success'])
            )
        ) {
            $error = true;
            $code = Code::BadRequest->value;
            $message = 'Failed';
            goto resp;
        }

        // Cache response
        Cache::put('NAS_SID', $response['data']['sid'], 60 * 2 * 60);

        resp:
        return apiResponse(
            generalResponse(
                $message,
                $error,
                $response,
                $code,
            ),
        );
    }

    public function folderList()
    {
        $response = HTTP::get(env('NAS_URL').'/entry.cgi', [
            '_sid' => Cache::get('NAS_SID'),
            'api' => 'SYNO.FileStation.List',
            'version' => 2,
            'method' => 'list_share',
        ]);

        $response = json_decode($response, true);

        return apiResponse(
            generalResponse(
                'Success',
                false,
                $response,
            ),
        );
    }

    /**
     * Store addon configuration
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAddonConfiguration(TestConnection $request)
    {
        return apiResponse($this->service->storeAddonConfiguration($request->validated()));
    }

    /**
     * Test connection by given nas configuration
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(TestConnection $request)
    {
        return apiResponse($this->service->testConnection($request->validated()));
    }

    public function addonConfiguration()
    {
        return apiResponse($this->service->addonConfiguration());
    }

    public function showAddons() {}
}
