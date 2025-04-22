<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
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
            $range = [Carbon::now()->subDay()->format('Y-m-d'), Carbon::now()->format('Y-m-d')];
            foreach ($range as $perDate) {
                $logPerDate = $this->readLogs($perDate);
                $output = array_merge($output, $logPerDate);
            }

            return apiResponse(
                generalResponse(
                    message: "Success",
                    data: $output
                )
            );
        } catch (\Throwable $th) {
            return apiResponse(
                errorResponse($th)
            );
        }
    }

    protected function readLogs(string $date)
    {
        $output = [];
        $entry = '';

        $path = storage_path("logs/laravel-{$date}.log");
        if (file_exists($path)) {
            $file = fopen($path, 'r');
            while(($line = fgets($file)) != false) {
                if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                    if ($entry && str_contains($entry, '.ERROR:')) {
                        $output[] = \Illuminate\Support\Str::limit($entry, 500);
                    }
                    $entry = $line;
                } else {
                    $entry .= $line;
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
        }

        $output =  array_reverse($output);

        return $output;
    }
}
