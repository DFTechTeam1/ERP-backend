<?php

namespace Modules\Production\Services;

use App\Enums\Production\ProjectStatus;
use App\Services\GeneralService;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\InteractiveProjectRepository;

class InteractiveProjectService
{
    private InteractiveProjectRepository $repo;

    private GeneralService $generalService;

    private PositionRepository $positionRepository;

    private EmployeeRepository $employeeRepository;

    /**
     * Construction Data
     */
    public function __construct(
        InteractiveProjectRepository $repo,
        GeneralService $generalService,
        PositionRepository $positionRepository,
        EmployeeRepository $employeeRepository
    ) {
        $this->repo = $repo;
        $this->generalService = $generalService;
        $this->positionRepository = $positionRepository;
        $this->employeeRepository = $employeeRepository;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );

            $paginated = $paginated->map(function ($item) {
                $item['project_date_text'] = date('d F Y', strtotime($item->project_date));
                $item['status_text'] = $item->status->label();
                $item['status_color'] = $item->status->color();
                $item['pic_name'] = '-';

                return $item;
            });

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

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, '*', [
                'boards',
            ]);

            $output = [
                'uid' => $data->uid,
                'name' => $data->name,
                'description' => $data->note,
                'status_raw' => $data->status->value,
                'status_text' => $data->status->label(),
                'status_color' => $data->status->color(),
                'project_date' => date('d F Y', strtotime($data->project_date)),
                'led_detail' => $data->led_detail,
                'pic_names' => '-',
                'teams' => [],
                'references' => [],
                'boards' => $data->boards->map(function ($board) {
                    return [
                        'id' => $board->id,
                        'name' => $board->name,
                        'tasks' => [],
                    ];
                }),
                'project_is_complete' => $data->status === ProjectStatus::Completed ? true : false,
                'permission_list' => [
                    'add_task' => true,
                ],
            ];

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
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
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
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
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
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
     * Get team list
     */
    public function getTeamList(): array
    {
        try {
            $teams = $this->generalService->getSettingByKey('interactive_team_positions');
            $teams = ! empty($teams) ? json_decode($teams, true) : [];

            // get posision id. Convert that uid to id
            $teams = collect($teams)->map(function ($team) {
                return $this->generalService->getIdFromUid($team, new PositionBackup);
            });

            $employees = $this->employeeRepository->list(
                select: 'id as value,name as text,email',
                where: 'position_id IN ('.implode(',', $teams->toArray()).')'
            );

            return generalResponse(
                'success',
                false,
                $employees->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
