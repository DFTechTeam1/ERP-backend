<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use Modules\Company\Repository\SettingRepository;

class SettingService {
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new SettingRepository;
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
                    if ($item['key'] == 'production_staff_role') {
                        $item['value'] = json_decode($item['value'], true);
                    } else if ($item['key'] == 'super_user_role' || $item['key'] == 'board_start_calcualted') {
                        $item['value'] = (int) $item['value'];
                    }

                    return $item;
                })->toArray();
                $settings = $selected;
            }
        } else {
            $settings = collect($settings)->map(function ($item) {
                if ($item['key'] == 'production_staff_role') {
                    $item['value'] = json_decode($item['value'], true);
                } else if ($item['key'] == 'default_boards') {
                    $item['value'] = $this->formatKanbanSetting($item);
                } else if ($item['key'] == 'position_as_directors' || $item['key'] == 'position_as_project_manager') {
                    $item['value'] = json_decode($item['value'], true);
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
    public function store(array $data, $code = null): array
    {
        try {
            if ($code == 'kanban') {
                $this->storeKanban($data);
            } else if ($code == 'email') {
                $this->storeEmail($data);
            } else if ($code == 'general') {
                $this->storeGeneral($data);
            } else if ($code == 'variables') {
                $this->storeVariables($data);
            }

            cachingSetting();

            $settings = $this->formattedGlobalSetting();

            return generalResponse(
                __("global.successUpdateSetting"),
                false,
                $settings
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function storeGeneral(array $data)
    {
        foreach ($data as $key => $value) {
            $valueData = gettype($value) == 'array' ? json_encode($value) : $value;

            $check = $this->repo->show('dummy', 'id', [], "key = '" . (string) $key . "'");
            if ($check) {
                $this->repo->update([
                    'value' => $valueData
                ], 'dummy', 'id = ' . $check->id);
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

    protected function storeVariables(array $data)
    {
        foreach ($data as $key => $value) {
            $valueData = gettype($value) == 'array' ? json_encode($value) : $value;

            $check = $this->repo->show('dummy', 'id', [], "key = '" . (string) $key . "'");
            if ($check) {
                $this->repo->update([
                    'value' => $valueData
                ], 'dummy', 'id = ' . $check->id);
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
            // change config
            if ($key == 'email_host') {
                \Illuminate\Support\Facades\Config::set("mail.mailers.smtp.host", $value);
            } else if ($key == 'email_port') {
                \Illuminate\Support\Facades\Config::set("mail.mailers.smtp.port", $value);
            } else if ($key == 'username') {
                \Illuminate\Support\Facades\Config::set("mail.mailers.smtp.username", $value);
            } else if ($key == 'password') {
                \Illuminate\Support\Facades\Config::set("mail.mailers.smtp.password", $value);
            }

            $this->repo->update([
                'value' => $value
            ], '', "key = '" . $key . "'");
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    /**
     * Store default kanban boards
     *
     * @param array $data
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