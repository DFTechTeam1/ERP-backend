<?php

namespace Modules\Company\Services;

use App\Enums\Production\TaskStatus;
use Modules\Company\Repository\SettingRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class SettingService
{
    private $repo;

    private $taskRepo;

    const LOGO_PATH = 'settings';

    /**
     * Construction Data
     */
    public function __construct(
        SettingRepository $repo,
        ProjectTaskRepository $taskRepo
    ) {
        $this->repo = $repo;

        $this->taskRepo = $taskRepo;
    }

    protected function formattedGlobalSetting($code = null)
    {
        $settings = \Illuminate\Support\Facades\Cache::get('setting');

        if ($code) {
            $selected = collect($settings)->where('code', $code)->values()->toArray();

            if ($code == 'kanban') {
                $settings = $this->formatKanbanSetting($selected);
            } else {
                $selected = collect($selected)->map(function ($item) {
                    if ($item['key'] == 'super_user_role' || $item['key'] == 'board_start_calcualted') {
                        $item['value'] = (int) $item['value'];
                    }

                    // format logo
                    if ($item['key'] == 'company_logo') {
                        $item['value'] = asset('storage/settings/'.$item['value']);
                    }

                    return $item;
                })->toArray();
                $settings = $selected;
            }
        } else {
            $settings = collect($settings)->map(function ($item) {
                if ($item['key'] == 'production_staff_role') {
                    $item['value'] = json_decode($item['value'], true);
                } elseif ($item['key'] == 'default_boards') {
                    $item['value'] = $this->formatKanbanSetting($item);
                } elseif ($item['key'] == 'position_as_directors' || $item['key'] == 'position_as_project_manager' || $item['key'] == 'position_as_production' || $item['key'] == 'position_as_visual_jokey' || $item['key'] == 'project_manager_role' || $item['key'] == 'director_role' || $item['key'] == 'role_as_entertainment') {
                    $item['value'] = json_decode($item['value'], true);
                }

                if (
                    ($item['key'] == 'company_logo') &&
                    (
                        ($item['value']) &&
                        (is_file(storage_path('app/public/settings/' . $item['value'])))
                    )
                ) {
                    $item['value'] = asset('storage/settings/' . $item['value']);
                }

                return $item;
            })->groupBy('code')->toArray();
        }

        return $settings;
    }

    public function getSetting($code = null)
    {
        $settings = $this->formattedGlobalSetting($code);

        return generalResponse(
            'success',
            false,
            $settings,
        );
    }

    public function getSettingByKeyAndCode(string $key, string $code)
    {
        $settings = \Illuminate\Support\Facades\Cache::get('setting');

        $selected = collect($settings)->filter(function ($filter) use ($key) {
            return $filter['key'] == $key;
        })->values()->toArray();

        $out = null;
        if (count($selected) > 0) {
            $out = $selected[0];
        }

        return generalResponse(
            'success',
            false,
            [
                'email' => $out,
            ],
        );
    }

    protected function formatKanbanSetting($setting)
    {
        $out = isset($setting[0]) ? $setting[0] : $setting;

        $kanban = json_decode($out['value'], true);
        $kanban = collect($kanban)->sortBy('sort')->values();

        return [
            'key' => $out['key'],
            'boards' => $kanban,
            'id' => $out['id'],
        ];
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
     */
    public function store(array $data, $code = null): array
    {
        try {
            if ($code == 'kanban') {
                $this->storeKanban($data);
            } elseif ($code == 'email') {
                $this->storeEmail($data);
            } elseif ($code == 'general') {
                $this->storeGeneral($data);
            } elseif ($code == 'variables') {
                $storeVariable = $this->storeVariables($data);
                if ($storeVariable) {
                    return $storeVariable;
                }
            } elseif ($code == 'company') {
                $this->storeCompany($data);
            }

            cachingSetting();

            $settings = $this->formattedGlobalSetting();

            return generalResponse(
                __('global.successUpdateSetting'),
                false,
                $settings
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function storeCompany(array $data)
    {
        // get current logo and delete if exists
        $currentLogo = $this->repo->show(uid: 'uid', select: 'value', where: "`key` = 'company_logo'");

        if (
            ($currentLogo) &&
            is_file(storage_path('app/public/'.self::LOGO_PATH."/{$currentLogo}"))
        ) {
            unlink(storage_path('app/public/'.self::LOGO_PATH."/{$currentLogo}"));
        }

        foreach ($data as $key => $value) {
            $check = $this->repo->show(uid: 'uid', select: 'id,value', where: "`key` = '{$key}'");

            if (($key == 'company_logo') && ($value)) {
                $image = uploadImageandCompress(
                    path: 'settings',
                    compressValue: 0,
                    image: $value
                );

                if ($image) {
                    $value = $image;
                } else {
                    $value = NULL;
                }
            }

            if ($check) {
                $this->repo->update(
                    data: [
                        'value' => $value,
                    ],
                    id: $check->id
                );
            } else {
                $this->repo->store(
                    data: [
                        'key' => $key,
                        'value' => $value,
                        'code' => 'company',
                    ]
                );
            }
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    protected function storeGeneral(array $data)
    {
        foreach ($data as $key => $value) {
            $this->repo->deleteByKey($key);

            $valueData = gettype($value) == 'array' ? json_encode($value) : $value;

            $keyQuery = config('app.env') == 'production' ? '`key` =' : 'key =';

            $where = "`key` = '".(string) $keyQuery."'";
            $check = $this->repo->show('dummy', 'id', [], $where);
            if ($check) {
                $this->repo->update([
                    'value' => $valueData,
                ], 'dummy', 'id = '.$check->id);
            } else {
                $this->repo->store([
                    'key' => $key,
                    'value' => $valueData,
                    'code' => 'general',
                ]);
            }
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    public function storeVariables(array $data)
    {
        $leadModellerTask = $this->taskRepo->show(
            uid: 0,
            select: 'id',
            where: 'status = '.TaskStatus::WaitingDistribute->value
        );
        if (empty($data['lead_3d_modeller']) || ! $data['lead_3d_modeller'] && $leadModellerTask) {
            return errorResponse('Lead 3D Modeller cannot be empty. There was some tasks that need to be done by Lead Modeller');
        }

        foreach ($data as $key => $value) {
            $this->repo->deleteByKey($key);

            $valueData = gettype($value) == 'array' ? json_encode($value) : $value;

            $keyQuery = config('app.env') == 'production' ? '`key` =' : 'key =';

            $where = "`key` = '".(string) $key."'";
            $check = $this->repo->show('dummy', 'id', [], $where);
            if ($check) {
                $this->repo->update([
                    'value' => $valueData,
                ], 'dummy', 'id = '.$check->id);
            } else {
                $this->repo->store([
                    'key' => $key,
                    'value' => $valueData,
                    'code' => 'variables',
                ]);
            }
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    protected function storeEmail(array $data)
    {
        foreach ($data as $key => $value) {
            $this->repo->deleteByKey($key);

            // change config
            if ($key == 'email_host') {
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.host', $value);
            } elseif ($key == 'email_port') {
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.port', $value);
            } elseif ($key == 'username') {
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.username', $value);
            } elseif ($key == 'password') {
                \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.password', $value);
            }

            $keyQuery = config('app.env') == 'production' ? '`key` =' : 'key =';

            $where = "`key` = '".(string) $key."'";
            logging('where store email', [$where]);
            $this->repo->store([
                'key' => $key,
                'value' => $value,
                'code' => 'email',
            ]);
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    /**
     * Store default kanban boards
     *
     * @return void
     */
    protected function storeKanban(array $data)
    {
        $boards = [];
        foreach ($data['boards'] as $key => $board) {
            $boards[] = $board;

            $boards[$key]['id'] = $key + 1;
        }

        $this->repo->updateOrInsert(
            ['code' => 'kanban'],
            [
                'key' => 'default_boards',
                'value' => json_encode($boards),
            ]
        );

        \Illuminate\Support\Facades\Cache::forget('setting');
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
}
