<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private $service;

    public function __construct(\App\Services\DashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function getReport()
    {
        return apiResponse($this->service->getReport());
    }

    public function getProjectSong(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getProjectSong());
    }

    public function needCompleteProject()
    {
        return apiResponse($this->service->needCompleteProject());
    }

    public function getProjectCalendar()
    {
        return apiResponse($this->service->getProjectCalendars());
    }

    public function getProjectDeadline()
    {
        return apiResponse($this->service->getProjectDeadline());
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

    /**
     * Function to get all logs
     * This function is only for email account that already registered in the config/allowed_email.php
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogs(): \Illuminate\Http\JsonResponse
    {
        try {
            $output = [];
            $entry = '';
            $file = File::lines(storage_path('logs/laravel.log'));
            Log::debug('file', [$file]);
            foreach ($file as $line) {
                    // New log entry starts with timestamp
                if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                    // Store previous entry if it was an ERROR
                    if ($entry && str_contains($entry, '.ERROR:')) {
                        $output[] = $entry;
                    }
                    $entry = $line;
                } else {
                    $entry .= "\n" . $line;
                }
            }

            if ($entry && str_contains($entry, '.ERROR:')) {
                $output[] = $entry;
            }

            // only return a view of characters
            $output = collect($output)->map(function ($mapping, $key) {
                $mapping = \Illuminate\Support\Str::limit($mapping, 500);

                return [
                    'log' => $mapping,
                    'id' => $key + 1
                ];
            })->all();
            $output = array_reverse($output);

            return apiResponse(
                generalResponse(
                    message: "Success",
                    data: $output
                )
            );
        } catch (\Throwable $th) {
            Log::debug("ERROR LOG", [$th]);
            return apiResponse(
                errorResponse($th)
            );
        }
    }
}
