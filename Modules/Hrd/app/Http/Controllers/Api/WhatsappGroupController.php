<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Data\Whatsapp\CreateCommunitySchemaData;
use App\Data\Whatsapp\CreateGroupSchemaData;
use App\Data\Whatsapp\MakeAsAdminData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Hrd\Http\Requests\WhatsappGroup\AddParticipant;
use Modules\Hrd\Http\Requests\WhatsappGroup\Update;
use Modules\Hrd\Services\WhatsappGroupService;

class WhatsappGroupController extends Controller
{
    public function __construct(private readonly WhatsappGroupService $service) {}

    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Create new whatsapp group
     *
     * @param CreateGroupSchemaData $request
     * @return JsonResponse
     */
    public function store(CreateGroupSchemaData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    /**
     * Update selected whatsapp group
     *
     * @param Update $request
     * @param integer $whatsapp_group
     * @return JsonResponse
     */
    public function update(Update $request, int $whatsapp_group): JsonResponse
    {
        return apiResponse($this->service->update($request->validated(), $whatsapp_group));
    }

    /**
     * Delete selected group
     *
     * @param integer $whatsapp_group
     * @return JsonResponse
     */
    public function destroy(int $whatsapp_group): JsonResponse
    {
        return apiResponse($this->service->delete($whatsapp_group));
    }

    /**
     * Get list available community
     *
     * @return JsonResponse
     */
    public function indexCommunity(): JsonResponse
    {
        return apiResponse($this->service->listCommunity());
    }

    /**
     * Delete community
     *
     * @param integer $community
     * @return JsonResponse
     */
    public function destroyCommunity(string $communityId): JsonResponse
    {
        return apiResponse($this->service->deleteCommunity($communityId));
    }

    /**
     * Create new community
     *
     * @param CreateCommunitySchemaData $request
     * @return JsonResponse
     */
    public function storeCommunity(CreateCommunitySchemaData $request): JsonResponse
    {
        return apiResponse($this->service->storeCommunity($request));
    }

    /**
     * Fetch comminity groups
     *
     * @param string $communityId
     * @return JsonResponse
     */
    public function communityGroups(string $communityId): JsonResponse
    {
        return apiResponse($this->service->listCommunityGroups($communityId));
    }

    public function sync(string $groupId): JsonResponse
    {
        return apiResponse($this->service->sync($groupId));
    }

    public function addParticipant(AddParticipant $request, string $groupId): JsonResponse
    {
        return apiResponse($this->service->addParticipant(
            $groupId,
            $request->validated('employee_uid'),
            $request->boolean('is_admin'),
        ));
    }

    /**
     * Update user role in the group
     *
     * @param MakeAsAdminData $request
     * @param string $groupId
     * @return JsonResponse
     */
    public function makeUserAsAdmin(MakeAsAdminData $request, string $groupId): JsonResponse
    {
        return apiResponse($this->service->makeUserAsAdmin($request, $groupId));
    }

    /**
     * Remove selected member from given group
     *
     * @param string $employeeUid
     * @param string $groupId
     * @return JsonResponse
     */
    public function removeMemberFromGroup(string $employeeUid, string $groupId): JsonResponse
    {
        return apiResponse($this->service->removeMemberFromGroup($employeeUid, $groupId));
    }

    /**
     * Get participants of the selected group
     *
     * @param string $groupId
     * @return JsonResponse
     */
    public function participantsGroup(string $groupId): JsonResponse
    {
        return apiResponse($this->service->participantsGroup($groupId));
    }

    public function getUserWhatsappGroup(string $employeeUid)
    {
        return apiResponse($this->service->getUserWhatsappGroup($employeeUid));
    }

    public function logs(): JsonResponse
    {
        $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

        return apiResponse($this->service->getLogs($itemsPerPage, $page));
    }

    public function sendTesting(string $groupId)
    {
        return apiResponse($this->service->sendTesting($groupId));
    }
}
