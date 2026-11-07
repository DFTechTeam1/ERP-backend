<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Company\Http\Requests\NotificationTemplate\Create;
use Modules\Company\Http\Requests\NotificationTemplate\Preview;
use Modules\Company\Http\Requests\NotificationTemplate\Update;
use Modules\Company\Services\NotificationTemplateService;

class NotificationTemplateController extends Controller
{
    public function __construct(private NotificationTemplateService $service) {}

    /**
     * GET /api/v1/notification-templates
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * GET /api/v1/notification-templates/metadata
     */
    public function metadata(): JsonResponse
    {
        return apiResponse($this->service->metadata());
    }

    /**
     * POST /api/v1/notification-templates/preview
     */
    public function preview(Preview $request): JsonResponse
    {
        $validated = $request->validated();

        return apiResponse($this->service->preview(
            template: $validated['template'],
            templateHtml: $validated['template_html'] ?? null,
            data: $validated['data'] ?? [],
        ));
    }

    /**
     * GET /api/v1/notification-templates/{id}
     */
    public function show(int $id): JsonResponse
    {
        return apiResponse($this->service->show($id));
    }

    /**
     * POST /api/v1/notification-templates
     */
    public function store(Create $request): JsonResponse
    {
        return apiResponse($this->service->store($request->validated()));
    }

    /**
     * PUT /api/v1/notification-templates/{id}
     */
    public function update(Update $request, int $id): JsonResponse
    {
        return apiResponse($this->service->update($request->validated(), $id));
    }

    /**
     * DELETE /api/v1/notification-templates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        return apiResponse($this->service->delete($id));
    }
}
