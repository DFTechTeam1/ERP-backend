<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\CreateTemplateData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrd\Services\SignatureService;

class SignatureController extends Controller
{
    public function __construct(
        private readonly SignatureService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('hrd::index');
    }

    public function listDocumentTypes(): JsonResponse
    {
        return apiResponse($this->service->listDocumentTypes());
    }

    public function storeDocumentTypes(CreateDocumentTypeData $payload): JsonResponse
    {
        return apiResponse($this->service->storeDocumentTypes($payload));
    }

    public function bulkCreateDocumentType(BulkCreateDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkCreateDocumentType($request));
    }

    public function bulkDeleteDocumentType(BulkDeleteDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkDeleteDocumentType($request));
    }

    public function updateDocumentType(UpdateDocumentTypeData $request, string $documentId): JsonResponse
    {
        return apiResponse($this->service->updateDocumentType($request, $documentId));
    }

    public function bulkEditDocumentType(BulkUpdateDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkEditDocumentType($request));
    }

    /**
     * Detect placeholder from document and available replacement variable
     *
     * @param DetectPlaceholderData $request
     * @return JsonResponse
     */
    public function detectPlaceholder(DetectPlaceholderData $request): JsonResponse
    {
        return apiResponse($this->service->detectPlaceholder($request));
    }

    /**
     * Create master document template
     *
     * @param CreateTemplateData $request
     * @return JsonResponse
     */
    public function createTemplate(CreateTemplateData $request): JsonResponse
    {
        return apiResponse($this->service->createTemplate($request));
    }

    /**
     * List of available master template documents
     *
     * @return JsonResponse
     */
    public function listTemplates(): JsonResponse
    {
        return apiResponse($this->service->listTemplates());
    }

    public function deleteTemplate(string $templateUid): JsonResponse
    {
        return apiResponse($this->service->deleteTemplate($templateUid));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('hrd::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('hrd::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('hrd::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
