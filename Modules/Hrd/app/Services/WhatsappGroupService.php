<?php

namespace Modules\Hrd\Services;

use App\Data\Whatsapp\CreateCommunitySchemaData;
use App\Data\Whatsapp\CreateCommunityServerSchemaData;
use App\Data\Whatsapp\CreateGroupSchemaData;
use App\Data\Whatsapp\CreateGroupServerSchemaData;
use App\Data\Whatsapp\GenerateInviteLinkServerData;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\WhatsappGroup;
use Modules\Hrd\Repository\WhatsappCommunityRepository;

class WhatsappGroupService
{
    public function __construct(
        private readonly WhatsappCommunityRepository $whatsappCommunityRepo,
        private readonly WhatsappService $whatsappService
    ) {}

    public function list(): array
    {
        try {
            $groups = WhatsappGroup::query()
                ->with('employee:id,name')
                ->orderBy('target_type')
                ->orderBy('group_name')
                ->get()
                ->map(fn (WhatsappGroup $group) => [
                    'id' => $group->id,
                    'group_name' => $group->group_name,
                    'group_id' => $group->group_id,
                    'invitation_link' => $group->invitation_link,
                    'target_type' => $group->target_type->value,
                    'employee_id' => $group->employee_id,
                    'leader_name' => $group->employee?->name,
                ]);

            return generalResponse(message: 'Success', data: $groups);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function store(CreateGroupSchemaData $data): array
    {
        try {
            // Create group in whatsapp
            $groupId = $this->whatsappService->createGroup(
                new CreateGroupServerSchemaData(
                    communityId: $data->community_id,
                    subject: $data->group_name,
                    participants: [],
                )
            );

            if (! $groupId['data']) {
                return errorResponse(message: 'Failed to create whatsapp group');
            }

            // Create invitation link
            $invitation = $this->whatsappService->getGroupInviteLink(
                new GenerateInviteLinkServerData(
                    groupId: $groupId['data']
                )
            );
            $invitationLink = $invitation['data']['link'] ?? 'https://chat.whatsapp.com/invite/xxxx';

            $group = WhatsappGroup::create([
                'group_name' => $data->group_name,
                'community_id' => $data->community_id,
                'target_type' => $data->target_type,
                'employee_id' => $data->target_type === 'team' ? $data->employee_uid : null,
                'group_id' => $groupId['data'],
                'invitation_link' => $invitationLink,
            ]);

            return generalResponse(message: __('global.successCreateData'), data: $group);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function update(array $data, int $id): array
    {
        try {
            $group = WhatsappGroup::findOrFail($id);

            $group->update([
                'group_name' => $data['group_name'],
                'group_id' => $data['group_id'],
                'invitation_link' => $data['invitation_link'],
                'target_type' => $data['target_type'],
                'employee_id' => $data['target_type'] === 'team' ? $data['employee_id'] : null,
            ]);

            return generalResponse(message: __('global.successUpdateData'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function delete(int $id): array
    {
        try {
            WhatsappGroup::findOrFail($id)->delete();

            return generalResponse(message: __('global.successDeleteData'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function listCommunity(): array
    {
        try {
            $communities = $this->whatsappCommunityRepo->list('id,subject,description,community_id,created_at');

            return generalResponse(message: 'Success', data: $communities->toArray());
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function deleteCommunity(int $id): array
    {
        try {
            $this->whatsappCommunityRepo->delete($id);

            return generalResponse(message: "Success delete community");
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function storeCommunity(CreateCommunitySchemaData $data): array
    {
        try {
            // Call whatsapp api
            $communityId = $this->whatsappService->createCommunity(new CreateCommunityServerSchemaData(
                subject: $data->subject,
                description: $data->description,
            ));

            if (! $communityId) {
                return generalResponse(
                    message: 'Failed to create whatsapp community',
                    data: []
                );
            }

            $this->whatsappCommunityRepo->store([
                'subject' => $data->subject,
                'description' => $data->description,
                'community_id' => $communityId,
            ]);

            return generalResponse(
                message: 'Success create whatsapp community',
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
