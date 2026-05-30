<?php

namespace Modules\Hrd\Services;

use App\Data\Whatsapp\CommunityGroupsListSchemaData;
use App\Data\Whatsapp\CommunityListSchemaData;
use App\Data\Whatsapp\CreateCommunitySchemaData;
use App\Data\Whatsapp\CreateCommunityServerSchemaData;
use App\Data\Whatsapp\CreateGroupSchemaData;
use App\Data\Whatsapp\CreateGroupServerSchemaData;
use App\Data\Whatsapp\GenerateInviteLinkServerData;
use Illuminate\Support\Facades\DB;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\EmployeeWhatsappGroup;
use Modules\Hrd\Models\WhatsappGroup;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\WhatsappCommunityRepository;

class WhatsappGroupService
{
    public function __construct(
        private readonly WhatsappCommunityRepository $whatsappCommunityRepo,
        private readonly WhatsappService $whatsappService,
        private readonly EmployeeRepository $employeeRepo
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
        DB::beginTransaction();
        try {
            $checkCommunity = $this->whatsappCommunityRepo->show(
                uid: '',
                select: 'id',
                where: "community_id = '{$data->community_id}'"
            );

            if (! $checkCommunity) {
                return errorResponse(message: 'Whatsapp community not found');
            }

            $employee = $this->employeeRepo->show(
                uid: $data->employee_uid,
                select: 'id,name,phone,is_phone_verified'
            );

            if (! $employee) {
                return errorResponse(message: 'Employee not found');
            }

            if ($employee && ! $employee->is_phone_verified) {
                return errorResponse(message: 'Employee whatsapp number is not verified');
            }

            // Create group in whatsapp
            $groupId = $this->whatsappService->createGroup(
                new CreateGroupServerSchemaData(
                    communityId: $data->community_id,
                    subject: $data->group_name,
                    participants: ["62{$employee->phone}"],
                )
            );

            logging('check again data group', [$groupId]);

            if (! $groupId['data']) {
                return errorResponse(message: 'Failed to create whatsapp group');
            }

            $groupIdString = \Illuminate\Support\Str::replace('@g.us', '', $groupId['data']);

            // Create invitation link
            $invitation = $this->whatsappService->getGroupInviteLink(
                new GenerateInviteLinkServerData(
                    groupId: $groupIdString
                )
            );
            $invitationLink = $invitation['data']['link'] ?? 'https://chat.whatsapp.com/invite/xxxx';

            $group = WhatsappGroup::create([
                'group_name' => $data->group_name,
                'community_id' => $data->community_id,
                'target_type' => $data->target_type,
                'employee_id' => $data->target_type === 'team' ? $employee->id : null,
                'group_id' => $groupIdString,
                'invitation_link' => $invitationLink,
            ]);

            // Add participant to group
            EmployeeWhatsappGroup::create([
                'employee_id' => $employee->id,
                'group_id' => $group->group_id,
            ]);

            DB::commit();

            return generalResponse(message: __('global.successCreateData'), data: []);
        } catch (\Throwable $th) {
            DB::rollBack();

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

    /**
     * List all whatsapp communities
     *
     * @return array <CommunityListSchemaData>
     */
    public function listCommunity(): array
    {
        try {
            $output = [];
            $communities = $this->whatsappCommunityRepo->list(
                select: 'id,subject,description,community_id,created_at',
                relation: [
                    'groups:id,community_id',
                ]
            );

            foreach ($communities as $community) {
                $output[] = new CommunityListSchemaData(
                    id: $community->id,
                    name: $community->subject,
                    communityId: $community->community_id,
                    group_count: $community->groups->count()
                );
            }

            return generalResponse(message: 'Success', data: $output);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * List all groups belonging to a specific community
     *
     * @return array<CommunityGroupsListSchemaData>
     */
    public function listCommunityGroups(string $communityId): array
    {
        try {
            $groups = WhatsappGroup::query()
                ->where('community_id', $communityId)
                ->with('employee:id,name')
                ->withCount('participants')
                ->get()
                ->map(fn (WhatsappGroup $group) => new CommunityGroupsListSchemaData(
                    id: $group->id,
                    name: $group->group_name,
                    groupId: $group->group_id,
                    participant_count: $group->participants_count,
                    pic: $group->employee ? (object) ['id' => $group->employee->id, 'name' => $group->employee->name] : null,
                ));

            return generalResponse(message: 'Success', data: $groups->toArray());
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function sync(string $groupId): array
    {
        try {
            $this->whatsappService->whatsappSync($groupId);

            return generalResponse(message: 'Success sync whatsapp group');
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function addParticipant(string $groupId, string $employeeUid, bool $isAdmin = false): array
    {
        DB::beginTransaction();
        try {
            $employee = $this->employeeRepo->show(
                uid: $employeeUid,
                select: 'id,name,phone,is_phone_verified'
            );

            if (! $employee) {
                return errorResponse(message: 'Employee not found');
            }

            if (! $employee->is_phone_verified) {
                return errorResponse(message: 'Employee whatsapp number is not verified');
            }

            $alreadyMember = EmployeeWhatsappGroup::query()
                ->where('group_id', $groupId)
                ->where('employee_id', $employee->id)
                ->exists();

            if ($alreadyMember) {
                return errorResponse(message: 'Employee is already a member of this group');
            }

            $result = $this->whatsappService->addToWhatsappGroup([
                'groupId' => $groupId,
                'phones' => ["62{$employee->phone}"],
                'makeAdmin' => $isAdmin ? 1 : 0
            ]);

            if (! ($result['success'] ?? false)) {
                DB::rollBack();
                return errorResponse(message: $result['message'] ?? 'Failed to add participant');
            }

            // Note: Whatsapp service already handle the process to auto adding participant to mysql database
            // So here we just need to check if exists, update the 'is_admin' field, if not exists then create new record

            // $check = EmployeeWhatsappGroup::query()
            //     ->where('group_id', $groupId)
            //     ->where('employee_id', $employee->id)
            //     ->first();
            // if ($check) {
            //     $check->update(['is_admin' => $isAdmin]);
            // } else {
            //     EmployeeWhatsappGroup::create([
            //         'employee_id' => $employee->id,
            //         'group_id' => $groupId,
            //         'is_admin' => $isAdmin,
            //     ]);
            // }

            DB::commit();

            return generalResponse(message: 'Success add participant', data: []);
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    public function deleteCommunity(int $id): array
    {
        try {
            $this->whatsappCommunityRepo->delete($id);

            return generalResponse(message: 'Success delete community');
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

            $payloadData = [
                'subject' => $data->subject,
                'description' => $data->description,
                'community_id' => \Illuminate\Support\Str::replace('@g.us', '', $communityId),
            ];

            $this->whatsappCommunityRepo->store($payloadData);

            return generalResponse(
                message: 'Success create whatsapp community',
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
