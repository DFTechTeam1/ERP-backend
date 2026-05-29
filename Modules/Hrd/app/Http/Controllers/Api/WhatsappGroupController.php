<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Data\Whatsapp\CreateCommunitySchemaData;
use App\Data\Whatsapp\CreateGroupSchemaData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Hrd\Http\Requests\WhatsappGroup\Store;
use Modules\Hrd\Http\Requests\WhatsappGroup\Update;
use Modules\Hrd\Services\WhatsappGroupService;

class WhatsappGroupController extends Controller
{
    public function __construct(private readonly WhatsappGroupService $service) {}

    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    public function store(CreateGroupSchemaData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    public function update(Update $request, int $whatsapp_group): JsonResponse
    {
        return apiResponse($this->service->update($request->validated(), $whatsapp_group));
    }

    public function destroy(int $whatsapp_group): JsonResponse
    {
        return apiResponse($this->service->delete($whatsapp_group));
    }

    public function indexCommunity(): JsonResponse
    {
        return apiResponse($this->service->listCommunity());
    }

    public function destroyCommunity(int $community): JsonResponse
    {
        return apiResponse($this->service->deleteCommunity($community));
    }

    public function storeCommunity(CreateCommunitySchemaData $request): JsonResponse
    {
        return apiResponse($this->service->storeCommunity($request));
    }
}
