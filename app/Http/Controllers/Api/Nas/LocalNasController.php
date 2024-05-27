<?php

namespace App\Http\Controllers\Api\Nas;

use App\Http\Controllers\Controller;
use App\Services\LocalNasService;
use Illuminate\Http\Request;

class LocalNasController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new LocalNasService();
    }

    public function sharedFolders()
    {
        return response()->json($this->service->getSharedFolders());
    }

    public function upload(Request $request)
    {
        return $this->service->uploadFile(
            $request->path, 
            $request->name, 
            $request->mime, 
            $request->targetPath
        );
    }
}
