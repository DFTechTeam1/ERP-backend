<?php

namespace Modules\Development\Services;

use App\Enums\Development\Project\ReferenceType;
use App\Enums\ErrorCode\Code;
use App\Services\GeneralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Development\app\Services\DevelopmentProjectCacheService;
use Modules\Development\Repository\DevelopmentProjectRepository;
use Modules\Hrd\Models\Employee;

class DevelopmentProjectService {
    private $repo;

    private GeneralService $generalService;

    private DevelopmentProjectCacheService $cacheService;

    private const MEDIAPATH = 'development/projects/references';

    /**
     * Construction Data
     */
    public function __construct(
        DevelopmentProjectRepository $repo,
        GeneralService $generalService,
        DevelopmentProjectCacheService $cacheService
    )
    {
        $this->repo = $repo;
        $this->generalService = $generalService;
        $this->cacheService = $cacheService;
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
            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            // $page = $page == 1 ? 0 : $page;
            // $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            // make filter as array
            $param = [];
            
            if (request('name')) {
                $param['name'] = request('name');
            }

            if (request('status')) {
                $param['status'] = request('status');
            }

            if (request('pics')) {
                $param['pics'] = request('pics');
            }

            if (request('start_date')) {
                $param['start_date'] = request('start_date');
            }

            if (request('end_date')) {
                $param['end_date'] = request('end_date');
            }

            $rawData = $this->cacheService->getFilteredProjects(filters: $param, page: $page, perPage: $itemsPerPage);
            $this->cacheService->invalidateAllCacheExceptBase();

            $paginated = $rawData['data'] ?? [];
            $totalData = $rawData['total'] ?? 0;

            // $paginated = $this->repo->pagination(
            //     $select,
            //     $where,
            //     $relation,
            //     $itemsPerPage,
            //     $page
            // );
            // $totalData = $this->repo->list('id', $where)->count();

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
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show(
                uid: $uid,
                select: 'id,uid,name,description,status,project_date,created_by',
                relation: [
                    'pics:id,development_project_id,employee_id',
                    'pics.employee:id,uid',
                    'references'
                ]
            );

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
     * @param array $data               With these following structure
     * - name: string
     * - description: string|null
     * - references: array|null
     * - pics: array|null
     * - project_date: string (format: Y-m-d)
     * @return array
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->store($data);

            // attach references if exists
            if (!empty($data['references'])) {
                // create new variables as payload to references table
                $payloadReferences = [];

                // upload image if type = media
                foreach ($data['references'] as $reference) {
                    $payloadReferences[] = [
                        'type' => $reference['type'],
                    ];

                    if ($reference['type'] === ReferenceType::Media->value) {
                        // handle media upload
                        $media = $this->generalService->uploadImageandCompress(
                            path: self::MEDIAPATH,
                            compressValue: 0,
                            image: $reference['image']
                        );
                        $payloadReferences[count($payloadReferences) - 1]['media_path'] = $media;
                    } else if ($reference['type'] === ReferenceType::Link->value) {
                        $payloadReferences[count($payloadReferences) - 1]['link'] = $reference['link'];
                        $payloadReferences[count($payloadReferences) - 1]['link_name'] = $reference['link_name'];
                    }
                }


                $project->references()->createMany($payloadReferences);
            }

            // attach pics if exists
            if (!empty($data['pics'])) {
                // get id of each employee id. Employee_id is string which is using uid
                $employees = collect($data['pics'])->map(function ($pic) {
                    return [
                        'employee_id' => $this->generalService->getIdFromUid($pic['employee_id'], new Employee()),
                    ];
                })->toArray();

                $project->pics()->createMany($employees);
            }

            // attach boards
            $defaultBoards = json_decode($this->generalService->getSettingByKey('default_boards'), true);
            $defaultBoards = collect($defaultBoards)->map(function ($item) {
                return [
                    'name' => $item['name'],
                ];
            })->values()->toArray();
            if ($defaultBoards) {
                $project->boards()->createMany($defaultBoards);
            }

            // push new data to current cache
            $this->cacheService->pushNewProjectToAllProjectCache($project->uid);

            DB::commit();

            return generalResponse(message: __('notification.successCreateDevelopmentProject'));
        } catch (\Throwable $th) {
            DB::rollBack();

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
     * What should be done in this function:
     * 1. Validate status. Only on hold and cancelled that can be deleted
     * 2. Remove all references from database, remove image references from storage folder
     * 3. Remove all tasks
     * 4. Remove all boards
     * 5. Remove project
     * 6. delete cache
     *
     * @param integer $id
     * 
     * @return array
     */
    public function delete(string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show(
                uid: $projectUid,
                select: 'id',
                relation: [
                    'references',
                ]
            );

            foreach ($project->references as $reference) {
                if ($reference->type == ReferenceType::Media->value) {
                    // check if file exists
                    if (Storage::disk('public')->exists(self::MEDIAPATH . '/' . $reference->media_path)) {
                        // delete file
                        Storage::disk('public')->delete(self::MEDIAPATH . '/' . $reference->media_path);
                    }
                }

                $reference->delete();
            }

            $project->pics()->delete();
            $project->tasks()->delete();
            $project->boards()->delete();
            $project->delete();

            // delete cache
            $this->cacheService->deleteSpecificProjectByUid(projectUid: $projectUid);
            $this->cacheService->invalidateAllCacheExceptBase();

            DB::commit();

            return generalResponse(
                __('notification.successDeleteDevelopmentProject'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
}