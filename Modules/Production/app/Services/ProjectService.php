<?php

namespace Modules\Production\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\DB;
use Modules\Production\Repository\ProjectRepository;

class ProjectService {
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new ProjectRepository;
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
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();

            $paginated = collect($paginated)->map(function ($item) use ($eventTypes, $classes) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                    ];
                })->pluck('name')->values()->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $eventType = '-';
                foreach ($eventTypes as $et) {
                    if ($et->value == $item->event_type) {
                        $eventType = $et->label();
                    }
                }

                $eventClass = '-';
                $eventClassColor = null;
                foreach ($classes as $class) {
                    if ($class->value == $item->classification) {
                        $eventClass = $class->label();
                        $eventClassColor = $class->color();
                    }
                }

                return [
                    'uid' => $item->uid,
                    'marketing' => $marketing,
                    'pic' => count($pics) > 0  ? implode(', ', $pics) : '-',
                    'name' => $item->name,
                    'project_date' => date('d F Y', strtotime($item->project_date)),
                    'venue' => $item->venue,
                    'event_type' => $eventType,
                    'led_area' => $item->led_area,
                    'event_class' => $eventClass,
                    'event_class_color' => $eventClassColor,
                ];
            });

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
     * Get Event Types list
     *
     * @return array
     */
    public function getEventTypes()
    {
        $data = \App\Enums\Production\EventType::cases();

        $out = [];
        foreach ($data as $d) {
            $out[] = [
                'value' => $d->value,
                'title' => ucfirst($d->value),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    /**
     * Get Classification list
     *
     * @return array
     */
    public function getClassList()
    {
        $data = \App\Enums\Production\Classification::cases();

        $out = [];
        foreach ($data as $d) {
            $out[] = [
                'value' => $d->value,
                'title' => $d->label(),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
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
            $data = $this->repo->show($uid, '*', [
                'marketing:id,name,employee_id',
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id',
                'boards:id,project_id,name,sort'
            ]);

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();

            $pics = collect($data->personInCharges)->map(function ($pic) {
                return [
                    'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                ];
            })->pluck('name')->values()->toArray();

            $marketing = $data->marketing ? $data->marketing->name : '-';

            $eventType = '-';
            foreach ($eventTypes as $et) {
                if ($et->value == $data->event_type) {
                    $eventType = $et->label();
                }
            }

            $eventClass = '-';
            $eventClassColor = null;
            foreach ($classes as $class) {
                if ($class->value == $data->classification) {
                    $eventClass = $class->label();
                    $eventClassColor = $class->color();
                }
            }

            $output = [
                'uid' => $data->uid,
                'name' => $data->name,
                'event_type' => $eventType,
                'event_type_raw' => $data->event_type,
                'event_class_raw' => $data->classification,
                'event_class' => $eventClass,
                'event_class_color' => $eventClassColor,
                'project_date' => date('d F Y', strtotime($data->project_date)),
                'venue' => $data->venue,
                'marketing' => $marketing,
                'pic' => implode(', ', $pics),
                'collaboration' => $data->collaboration,
                'note' => $data->note ?? '-',
                'led_area' => $data->led_area,
                'led_detail' => json_decode($data->led_detail, true),
                'client_portal' => $data->client_portal,
                'status' => $data->status_text,
                'status_color' => $data->status_color,
                'boards' => $data->boards,
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
     *
     * @param array $data
     * 
     * @return array
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $data['project_date'] = date('Y-m-d', strtotime($data['project_date']));
            $data['led_detail'] = json_encode($data['led']);
            $data['marketing_id'] = getIdFromUid($data['marketing_id'], new \Modules\Hrd\Models\Employee());
            
            $userRole = auth()->user()->getRoleNames()[0];
            $data['status'] = strtolower($userRole) != 'project manager' ? \App\Enums\Production\ProjectStatus::OnGoing->value : \App\Enums\Production\ProjectStatus::Draft->value;

            $project = $this->repo->store(collect($data)->except(['led'])->toArray());

            $pics = collect($data['pic'])->map(function ($item) {
                return [
                    'pic_id' => getidFromUid($item, new \Modules\Hrd\Models\Employee()),
                ];
            })->toArray();
            $project->personInCharges()->createMany($pics);

            $defaultBoards = json_decode(getSettingByKey('default_boards'), true);
            if ($defaultBoards) {
                $project->boards()->createMany($defaultBoards);
            }

            DB::commit();

            return generalResponse(
                __('global.successCreateProject'),
                false,
                $data,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update basic project information
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function updateBasic(array $data, string $id)
    {
        try {
            $data['project_date'] = date('Y-m-d', strtotime($data['date']));
            
            $this->repo->update(
                collect($data)->except(['date'])->toArray(),
                $id
            );

            $output = [];
            $project = $this->repo->list('id,name,project_date,event_type,classification', "uid = '{$id}'", ['personInCharges:id,project_id,pic_id', 'personInCharges.employee:id,name,employee_id']);
            if (count($project) > 0) {
                $project = $project[0];
                $pic = collect($project['personInCharges'])->map(function ($item) {
                    return [
                        'name' => $item->employee->name . "(" . $item->employee->employee_id . ")",
                    ];
                })->pluck('name')->toArray();

                $output = [
                    'name' => $project->name,
                    'pic' => implode(', ', $pic),
                    'event_type_raw' => $project->event_type,
                    'event_type' => $project->event_type_text,
                    'event_class_raw' => $project->classification,
                    'event_class' => $project->event_class_text,
                    'event_class_color' => $project->event_class_color,
                    'project_date' => date('d F Y', strtotime($project->project_date)),
                ]; 
            }

            return generalResponse(
                __('global.successUpdateBasicInformation'),
                false,
                $output,
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