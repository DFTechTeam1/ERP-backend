<?php

namespace Modules\Hrd\Services;

use App\Data\Whatsapp\CommunityGroupsListSchemaData;
use App\Data\Whatsapp\CommunityListSchemaData;
use App\Data\Whatsapp\CreateCommunitySchemaData;
use App\Data\Whatsapp\CreateCommunityServerSchemaData;
use App\Data\Whatsapp\CreateGroupSchemaData;
use App\Data\Whatsapp\CreateGroupServerSchemaData;
use App\Data\Whatsapp\GenerateInviteLinkServerData;
use App\Data\Whatsapp\MakeAsAdminData;
use App\Data\Whatsapp\ParticipantsGroupData;
use App\Data\Whatsapp\PromoteUserData;
use App\Data\Whatsapp\UserWhatsappGroupData;
use App\Data\Whatsapp\WhatsappLogData;
use Illuminate\Support\Facades\DB;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\EmployeeWhatsappGroup;
use Modules\Hrd\Models\WhatsappGroup;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeWhatsappGroupRepository;
use Modules\Hrd\Repository\WhatsappCommunityRepository;
use Modules\Hrd\Repository\WhatsappGroupRepository;
use Modules\Hrd\Repository\WhatsappLogRepository;

class WhatsappGroupService
{
    public function __construct(
        private readonly WhatsappCommunityRepository $whatsappCommunityRepo,
        private readonly WhatsappService $whatsappService,
        private readonly EmployeeRepository $employeeRepo,
        private readonly EmployeeWhatsappGroupRepository $employeeWhatsappGroupRepo,
        private readonly WhatsappGroupRepository $whatsappGroupRepo,
        private readonly WhatsappLogRepository $whatsappLogRepo,
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
     * Get list of particpants of the selected group
     *
     * @param string $groupId
     * @return array
     */
    public function participantsGroup(string $groupId): array
    {
        try {
            /** @var array<int, ParticipantsGroupData> */
            $data = $this->employeeWhatsappGroupRepo->get([
                'where' => [
                    "group_id" => $groupId
                ],
                'select' => ["id", "employee_id", "group_id", "is_admin"],
                'with' => [
                    'employee:id,uid,name,phone',
                    'parentGroup:id,group_name'
                ]
            ])->map(function ($item) {
                return new ParticipantsGroupData(
                    id: $item->id,
                    employee_uid: $item->employee->uid,
                    name: $item->employee->name,
                    phone: $item->employee->phone,
                    is_admin: $item->is_admin ? true : false
                );
            })->toArray();

            return generalResponse(
                message: "Success",
                data: $data
            );
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

    /**
     * Reconcile the local `employee_whatsapp_groups` records with the live WhatsApp
     * group membership: adds employees newly present, removes those who left, and
     * updates the `isAdmin` flag where it changed. Matching is done by phone number.
     *
     * @param string $groupId
     * @return array
     */
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

    public function makeUserAsAdmin(MakeAsAdminData $payload, string $groupId): array
    {
        try {
            $employee = $this->employeeRepo->show(
                uid: $payload->employee_uid,
                select: 'id,phone'
            );

            $action = $this->whatsappService->setUserAsAdmin(new PromoteUserData(
                phone: "62{$employee->phone}",
                groupId: $groupId,
                isDemote: !$payload->is_admin ? true : false
            ));

            if (! $action['success']) return errorResponse(message: $action['message']);

            $employeeGroup = $this->employeeWhatsappGroupRepo->show([
                'where' => [
                    'employee_id' => $employee->id
                ]
            ]);
            
            $this->employeeWhatsappGroupRepo->update(
                $employeeGroup, 
                ['is_admin' => $payload->is_admin]
            );

            return generalResponse(
                message: "Success update user role"
            );
        } catch (\Throwable $th) {
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

    public function deleteGroup(string $groupId)
    {

    }

    public function removeMemberFromGroup(string $employeeUid, string $groupId)
    {
        DB::beginTransaction();
        try {
            $employee = $this->employeeRepo->show(uid: $employeeUid, select: 'id,phone');

            // Check if selected user is the PIC of the group
            $whatsappGroup = $this->whatsappGroupRepo->show([
                'where' => [
                    'group_id' => $groupId,
                    'employee_id' => $employee->id
                ],
                'select' => 'id'
            ]);

            // if ($whatsappGroup->exists()) {
            //     return errorResponse(message: "Cannot remove PIC of the group. Update the PIC first before delete");
            // }

            $employeeWhatsappGroup = $this->employeeWhatsappGroupRepo->show([
                'where' => [
                    'group_id' => $groupId,
                    'employee_id' => $employee->id
                ],
                'select' => 'id,group_id',
                'with' => [
                    'parentGroup:id,group_name,group_id'
                ]
            ]);

            if (! $employeeWhatsappGroup->exists()) {
                return errorResponse(message: "Employee is not found in the group");
            }

            $groupName = $employeeWhatsappGroup->parentGroup->group_name;

            // Remove from group
            $remove = $this->whatsappService->removeFromWhatsappGroup([
                'groupId' => $groupId,
                'phones' => [
                    "62{$employee->phone}"
                ]
            ]);

            if (! $this->isActionSuccess($remove)) {
                return errorResponse($remove['message'] ?? 'Failed to process the transaction');
            }

            // Remove from database
            $this->employeeWhatsappGroupRepo->delete($employeeWhatsappGroup);

            DB::commit();

            return generalResponse(
                message: "User has been removed from {$groupName}"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function isActionSuccess(array $response)
    {
        $output = true;

        if (!isset($response['success'])) return false;

        if (isset($response['success']) && ! $response['success']) return false;

        return $output;
    }

    public function getUserWhatsappGroup(string $employeeUid)
    {
        try {
            $employee = $this->employeeRepo->show(uid: $employeeUid, select: 'id,phone,name,uid,boss_id');

            /** @var array<int, UserWhatsappGroupData> */
            $output = [];

            $general = $this->whatsappGroupRepo->getGlobalGroup();

            if ($employee->boss_id) {
                $team = $this->whatsappGroupRepo->getBossWhatsappGroup($employee->boss_id);
            }

            $merge = $general->merge($team ?? [])->map(function ($group) use ($employee) {
                return new UserWhatsappGroupData(
                    id: $group->id,
                    group_id: $group->group_id,
                    name: $group->group_name,
                    type: $group->target_type->value,
                    joined: collect($group->participants)->search(function ($find) use ($employee) {
                        return $find->employee_id === $employee->id;
                    }, true),
                    invitation_link: $group->invitation_link
                );
            })->toArray();

            return generalResponse(
                message: "Success",
                data: $merge
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getLogs(int $itemsPerPage, int $page): array
    {
        try {
            /** @var array <int, WhatsappLogData> */
            $data = $this->whatsappLogRepo->get([
                'select' => '*',
                'take' => $itemsPerPage,
                'skip' => $page
            ])->map(function ($item) {
                return new WhatsappLogData(
                    to: $item->to,
                    text: $item->text,
                    service_type: $item->service_type,
                    action_type: $item->action_type,
                    response: $item->response,
                    created_at: $item->created_at,
                );
            })->toArray();

            return generalResponse(
                message: "Success",
                data: $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
