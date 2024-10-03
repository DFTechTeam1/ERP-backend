<?php

namespace Modules\Production\Services;

use App\Enums\Production\TransferTeamStatus;
use Carbon\Carbon;
use App\Enums\ErrorCode\Code;
use \Illuminate\Support\Facades\DB;
use Modules\Production\Repository\TransferTeamMemberRepository;

class TransferTeamMemberService {
    private $repo;

    private $projectService;

    private $employeeRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new TransferTeamMemberRepository;

        $this->employeeRepo = new \Modules\Hrd\Repository\EmployeeRepository();

        $this->projectService = new \Modules\Production\Services\ProjectService;
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     *
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $user = auth()->user();
            $roles = $user->roles;
            $roleId = $roles[0]->id;
            if ($roleId != getSettingByKey('super_user_role')) {
                if (empty($where)) {
                    $where = "request_to = {$user->employee_id} or requested_by = {$user->employee_id}";
                } else {
                    $where .= " and request_to = {$user->employee_id} or requested_by = {$user->employee_id}";
                }
            }

            $relation = [
                'employee:id,uid,name,email',
                'alternativeEmployee:id,uid,name,email',
                'requestToPerson:id,uid,name,email',
                'requestByPerson:id,uid,name,email',
                'project:id,uid,name',
            ];

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );

            $paginated = collect($paginated)->map(function ($item) use($user) {
                $item['project_date'] = date('d F Y', strtotime($item->project_date));

                $haveCancelAction = $item->requested_by == $user->employee_id ? true : false;

                $haveApproveAction = $item->request_to == $user->employee_id ? true : false;

                $haveAction = $item->status == \App\Enums\Production\TransferTeamStatus::Canceled->value || $item->status == \App\Enums\Production\TransferTeamStatus::Completed->value || $item->status == \App\Enums\Production\TransferTeamStatus::Reject->value || $item->status == \App\Enums\Production\TransferTeamStatus::ApprovedWithAlternative->value ? true : false;

                $isApproved = $item->status == \App\Enums\Production\TransferTeamStatus::Approved->value || $item->status == \App\Enums\Production\TransferTeamStatus::ApprovedWithAlternative->value ? true : false;

                $alternative = null;
                if ($item->alternative_employee_id) {
                    $alternative = [
                        'uid' => $item->alternativeEmployee->uid,
                        'name' => $item->alternativeEmployee->name,
                        'email' => $item->alternativeEmployee->email,
                    ];
                }

                $employeeFormat = null;
                if ($item->employee) {
                    $employeeFormat = [
                        'uid' => $item->employee->uid,
                        'name' => $item->employee->name,
                        'email' => $item->employee->email,
                    ];
                }

                return [
                    'uid' => $item->uid,
                    'is_approved' => $isApproved,
                    'employee' => $employeeFormat,
                    'requestTo' => [
                        'uid' => $item->requestToPerson->uid,
                        'name' => $item->requestToPerson->name,
                        'email' => $item->requestToPerson->email,
                    ],
                    'requestBy' => [
                        'uid' => $item->requestByPerson->uid,
                        'name' => $item->requestByPerson->name,
                        'email' => $item->requestByPerson->email,
                    ],
                    'alternative' => $alternative,
                    'reason' => $item->reason,
                    'project' => $item->project->name,
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'status_raw' => $item->status,
                    'have_cancel_action' => $haveCancelAction,
                    'have_approve_action' => $haveApproveAction,
                    'have_action' => $haveAction,
                ];
            })->all();

            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Cancel team request
     *
     * @param array $data
     * @return array
     */
    public function cancelRequest(array $data): array
    {
        try {
            foreach ($data['ids'] as $id) {
                $this->repo->update([
                    'status' => \App\Enums\Production\TransferTeamStatus::Canceled->value,
                    'canceled_at' => Carbon::now(),
                ], $id);

                \Modules\Production\Jobs\CancelRequestTeamMemberJob::dispatch($id);
            }

            return generalResponse(
                __('global.successCancelRequest'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to approve team request from other PIC
     *
     * Step:
     * 1. Change request status
     * 2. Insert new team member to requested PIC by updating project detail cache if exists
     *
     * @param string $transferUid
     * @return array
     */
    public function approveRequest(string $transferUid, string $deviceAction): array
    {
        DB::beginTransaction();
        try {
            $this->repo->update([
                'status' => \App\Enums\Production\TransferTeamStatus::Approved->value,
                'approved_at' => Carbon::now(),
                'device_action' => $deviceAction,
            ], $transferUid);

            $transfer = $this->repo->show($transferUid, 'id,project_id', ['project:id,uid']);

            $this->projectService->updateDetailProjectFromOtherService($transfer->project->uid);

            \Modules\Production\Jobs\ApproveRequestTeamMemberJob::dispatch([$transfer->id])->afterCommit();

            DB::commit();

            return generalResponse(
                __("global.transferTeamApproved"),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function completeRequest(string $transferUid): array
    {
        try {
            $this->repo->update([
                'status' => \App\Enums\Production\TransferTeamStatus::Completed->value,
                'completed_at' => Carbon::now(),
            ], $transferUid);

            $transfer = $this->repo->show($transferUid, 'id,project_id,employee_id', ['project:id,uid', 'employee:id,nickname']);

            $this->projectService->updateDetailProjectFromOtherService($transfer->project->uid);

            \Modules\Production\Jobs\CompleteRequestTeamMemberJob::dispatch($transferUid);

            return generalResponse(
                __('global.transferIsCompleted', ['name' => $transfer->employee->nickname]),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function rejectRequest(array $data, string $transferUid)
    {
        try {
            $this->repo->update([
                'status' => isset($data['alternative']) ? \App\Enums\Production\TransferTeamStatus::ApprovedWithAlternative->value : \App\Enums\Production\TransferTeamStatus::Reject->value,
                'rejected_at' => Carbon::now(),
                'reject_reason' => $data['reason'],
                'alternative_employee_id' => isset($data['alternative']) ? getIdFromUid($data['alternative'], new \Modules\Hrd\Models\Employee()) : null,
            ], $transferUid);

            \Modules\Production\Jobs\RejectRequestTeamMemberJob::dispatch($transferUid, $data['reason']);

            return generalResponse(
                __("global.requestIsRejected"),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param string $id
     * @param string $where
     *
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     * @param integer $id
     *
     * @return array
     */
    public function delete(string $transferUid): array
    {
        try {
            // cancel first
            $transfer = $this->repo->show($transferUid);

            if ($transfer->status == \App\Enums\Production\TransferTeamStatus::Requested->value) {
                $this->cancelRequest(['ids' => [$transferUid]]);
            }

            $this->repo->delete(getIdFromUid($transferUid, new \Modules\Production\Models\TransferTeamMember()));

            return generalResponse(
                __("global.teamRequestIsDelete"),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     *
     * @param array $ids
     *
     * @return array
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to get all team members except current employee id that already requested for lend
     *
     * @param string $employeeUid
     *
     * @return array
     */
    public function getMembersToLend(string $transferUid, string $employeeUid): array
    {
        $transfer = $this->repo->show($transferUid, 'request_to');

        $data = $this->employeeRepo->list("id,uid,name,employee_id", "uid != '" . $employeeUid . "' and boss_id = {$transfer->request_to}");

        $output = collect((object)$data)->map(function ($item) {
            return [
                'uid' => $item->uid,
                'name' => $item->name . " ({$item->employee_id})",
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Approved and choose team member to work on selected project
     *
     * @param array $payload
     * @param string $transferUid
     *
     * @return array
     */
    public function chooseTeam(array $payload, string $transferUid): array
    {
        DB::beginTransaction();
        try {
            $currentData = $this->repo->show($transferUid);

            // validate action
            $user = auth()->user();
            if (
                (!$user->is_directory || $user->email != config('app.root_email')) &&
                $user->employee_id != $currentData->request_to
            ) {
                throw new \App\Exceptions\InvalidPermissionAction();
            }

            $transferIds = [];
            foreach ($payload['team'] as $team) {
                $employeeId = getIdFromUid($team, new \Modules\Hrd\Models\Employee());

                $transferIds[] = $this->repo->store([
                    'is_entertainment' => true,
                    'project_id' => $currentData->project_id,
                    'employee_id' => $employeeId,
                    'request_to' => $currentData->request_to,
                    'requested_by' => $currentData->requested_by,
                    'request_at' => $currentData->request_at,
                    'reason' => $currentData->reason,
                    'project_date' => $currentData->project_date,
                    'approved_at' => Carbon::now(),
                    'status' => \App\Enums\Production\TransferTeamStatus::Approved->value,
                ]);
            }

            // delete current transfer id and store the new one
            $this->repo->delete($currentData->id);

            \Modules\Production\Jobs\ApproveRequestTeamMemberJob::dispatch($transferIds)->afterCommit();

            DB::commit();

            return generalResponse(
                __('notification.teamHasBeenDeterminedForTransfer'),
                false,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return errorResponse($e);
        }
    }

    public function approveSelection(array $payload, string $transferUid): array
    {
        DB::beginTransaction();
        try {
            // create a new record with current data
            $currentData = $this->repo->show($transferUid);

            foreach ($payload['ids'] as $id) {
                $newData = $currentData->replicate();
                $newData->employee_id = getIdFromUid($id, new \Modules\Hrd\Models\Employee());
                $newData->approved_at = Carbon::now();
                $newData->is_entertainment = 1;
                $newData->status = TransferTeamStatus::Approved->value;
                $newData->save();

                \Modules\Production\Jobs\ApproveRequestTeamMemberJob::dispatch([$newData->id])->afterCommit();
            }

            $currentData->delete();

            DB::commit();

            return generalResponse(
                __('notification.successApproveAndChooseMemberTransfer'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }
}
