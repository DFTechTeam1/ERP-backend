<?php

namespace Modules\Company\Services;

use App\Enums\Production\TaskStatus;
use Modules\Company\Repository\SettingRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class SettingService
{
    private $repo;

    private $taskRepo;

    private $generalService;

    const LOGO_PATH = 'settings';

    /**
     * Construction Data
     */
    public function __construct(
        SettingRepository $repo,
        ProjectTaskRepository $taskRepo,
        \App\Services\GeneralService $generalService
    ) {
        $this->repo = $repo;

        $this->taskRepo = $taskRepo;

        $this->generalService = $generalService;
    }

    protected function formattedGlobalSetting($code = null)
    {
        $settings = \Illuminate\Support\Facades\Cache::get('setting');

        // format guide price
        $settings = collect($settings)->map(function ($setting) {
            if ($setting['key'] == 'area_guide_price') {
                $setting['value'] = json_decode($setting['value'], true);
            }

            return $setting;
        });

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
                } elseif ($item['key'] == 'position_as_directors' || $item['key'] == 'position_as_project_manager' || $item['key'] == 'position_as_production' || $item['key'] == 'position_as_visual_jokey' || $item['key'] == 'project_manager_role' || $item['key'] == 'director_role' || $item['key'] == 'role_as_entertainment' || $item['key'] == 'person_to_approve_invoice_changes' || $item['key'] == 'interactive_pic' || $item['key'] == 'position_in_interactive_task' || $item['key'] == 'person_to_approve_interactive_event' || $item['key'] == 'position_as_marcomm' || $item['key'] == 'marcomm_pic' || $item['key'] == 'interactive_employees') {
                    $item['value'] = json_decode($item['value'], true);
                }

                if (
                    ($item['key'] == 'company_logo') &&
                    (
                        ($item['value']) &&
                        (is_file(storage_path('app/public/settings/'.$item['value'])))
                    )
                ) {
                    $item['value'] = asset('storage/settings/'.$item['value']);
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
            } elseif ($code == 'price') {
                $this->storePricing($data);
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

    protected function storePricing(array $payload): void
    {
        $checkCurrentSetting = $this->repo->show(
            uid: 'uid',
            select: 'id,value',
            where: "`key` = 'area_guide_price'"
        );

        $payload['area'] = collect($payload['area'])->map(function ($area) {
            // change setting value. Remove . or , symbol
            $area['settings'] = collect($area['settings'])->map(function ($setting) {
                if (isset($setting['value'])) {
                    $setting['value'] = str_replace(['.', ','], '', $setting['value']);
                }

                return $setting;
            })->toArray();

            return $area;
        });

        // Remove . or , symbol from high season and price up and equipment
        if (isset($payload['high_season']['value'])) {
            $payload['high_season']['value'] = str_replace(['.', ','], '', $payload['high_season']['value']);
        }
        if (isset($payload['price_up']['value'])) {
            $payload['price_up']['value'] = str_replace(['.', ','], '', $payload['price_up']['value']);
        }
        if (isset($payload['equipment'])) {
            $payload['equipment'] = collect($payload['equipment'])->map(function ($equipment) {
                if (isset($equipment['value'])) {
                    $equipment['value'] = str_replace(['.', ','], '', $equipment['value']);
                }

                return $equipment;
            })->toArray();
        }

        // update if exists and create if not exists
        if ($checkCurrentSetting) {
            $this->repo->update(
                data: [
                    'value' => json_encode($payload),
                ],
                id: $checkCurrentSetting->id
            );
        } else {
            $this->repo->store(
                data: [
                    'key' => 'area_guide_price',
                    'value' => json_encode($payload),
                    'code' => 'price',
                ]
            );
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    protected function storeCompany(array $data): void
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
                    $value = null;
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

    /**
     * Get price calculation for project deals
     */
    public function getPriceCalculation(): array
    {
        try {
            $output = [];

            $guides = $this->generalService->getSettingByKey(param: 'area_guide_price');

            if ($guides) {
                $guides = json_decode($guides, true);

                $areaPricing = [];
                $areas = [];

                foreach ($guides['area'] as $area) {
                    $areas[] = [
                        'title' => $area['area'],
                        'value' => strtolower(str_replace(' ', '_', $area['area'])),
                    ];

                    $settings = [];
                    foreach ($area['settings'] as $setting) {
                        if ($setting['name'] == 'Main Ballroom Fee') {
                            $settings['mainBallroom'] = [
                                'fixed' => '{total_led}*'.$setting['value'],
                                'percentage' => null,
                            ];
                        } elseif ($setting['name'] == 'Prefunction Fee') {
                            $percent = 100 - $guides['prefunction_percentage'];
                            $settings['prefunction'] = [
                                'fixed' => '{total_led}*('.$setting['value'].'*'.$percent.'/100)',
                                'percentage' => null,
                            ];
                        } elseif ($setting['name'] == 'Max Discount') {
                            $percentage = null;
                            $fixed = null;
                            if ($setting['type'] == 'percentage') {
                                $percentage = '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})*'.$setting['value'].'/100';
                            } elseif ($setting['type'] == 'fixed') {
                                $fixed = '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})-'.$setting['value'];
                            }
                            $settings['discount'] = [
                                'percentage' => $percentage,
                                'fixed' => $fixed,
                            ];
                        }
                    }

                    $formattedName = strtolower(str_replace(' ', '_', $area['area']));
                    $areaPricing['areaGuide'][$formattedName] = $settings;
                }

                $output = array_merge($output, $areaPricing);

                // area
                $output['area'] = $areas;

                // high season fee
                $output['highSeason'] = [
                    'percentage' => $guides['high_season']['type'] == 'percentage' ? '({main_ballroom_price}+{prefunction_price})*'.$guides['high_season']['value'].'/100' : null,
                    'fixed' => $guides['high_season']['type'] == 'fixed' ? $guides['high_season']['value'] : null,
                ];

                // markup
                $output['markup'] = [
                    'percentaage' => $guides['price_up']['type'] == 'percentage' ? '{total_contract}*'.$guides['price_up']['value'].'/100' : null,
                    'fixed' => $guides['price_up']['type'] == 'fixed' ? '{total_contract}+'.$guides['price_up']['value'] : null,
                ];

                // equipment
                $output['equipment'] = [
                    'lasika' => collect($guides['equipment'])->filter(function ($filter) {
                        return $filter['name'] == 'Lasika';
                    })->values()[0]['value'],
                    'others' => collect($guides['equipment'])->filter(function ($filter) {
                        return $filter['name'] == 'Others';
                    })->values()[0]['value'],
                ];

                // minimum price
                $output['minimum_price'] = $guides['minimum_price'];

                // equipment list
                $output['equipmentList'] = collect($guides['equipment'])->map(function ($map) {
                    return [
                        'title' => $map['name'],
                        'value' => strtolower($map['name']),
                    ];
                });

                // $output = [
                //     'surabaya' => [
                //         'mainBallroom' => [
                //             'fixed' => '{total_led}*750000',
                //             'percentage' => null,
                //         ],
                //         'prefunction' => [
                //             'fixed' => '{total_led}*(750000*75/100)',
                //             'percentage' => null,
                //         ],
                //         'discount' => [
                //             'percentage' => '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})*10/100',
                //             'fixed' => null,
                //         ],
                //     ],
                //     'jakarta' => [
                //         'mainBallroom' => [
                //             'fixed' => '{total_led}*1250000',
                //             'percentage' => null,
                //         ],
                //         'prefunction' => [
                //             'fixed' => '{total_led}*(1250000*75/100)',
                //             'percentage' => null,
                //         ],
                //         'discount' => [
                //             'percentage' => '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})*10/100',
                //             'fixed' => null,
                //         ],
                //     ],
                //     'jawa' => [
                //         'mainBallroom' => [
                //             'fixed' => '{total_led}*500000',
                //             'percentage' => null,
                //         ],
                //         'prefunction' => [
                //             'fixed' => '{total_led}*(500000*75/100)',
                //             'percentage' => null,
                //         ],
                //         'discount' => [
                //             'percentage' => '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})*10/100',
                //             'fixed' => null,
                //         ],
                //     ],
                //     'luar_jawa' => [
                //         'mainBallroom' => [
                //             'fixed' => '{total_led}*1000000',
                //             'percentage' => null,
                //         ],
                //         'prefunction' => [
                //             'fixed' => '{total_led}*(1000000*75/100)',
                //             'percentage' => null,
                //         ],
                //         'discount' => [
                //             'percentage' => '({main_ballroom_price}+{prefunction_price}+{high_season_price}+{equipment_price})*10/100',
                //             'fixed' => null,
                //         ],
                //     ],
                //     'highSeason' => [
                //         'percentage' => '({main_ballroom_price}+{prefunction_price})*25/100',
                //         'fixed' => null,
                //     ],
                //     'equipment' => [
                //         'lasika' => 0,
                //         'others' => '2500000',
                //     ],
                //     'equipmentList' => [
                //         [
                //             'title' => 'Lasika',
                //             'value' => 'lasika',
                //         ],
                //         [
                //             'title' => 'Others',
                //             'value' => 'others',
                //         ],
                //     ],
                //     'markup' => [
                //         'percentage' => '{(main_ballroom_price+prefunction_price)*11/100}',
                //         'fixed' => null,
                //     ],
                //     'minimum_price' => '35000000',
                // ];
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get application logs
     * @return array
     */
    public function getLogs(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage', 100); // Default 100 logs per page
            $page = request('page', 1); // Default page 1
            $level = request('level'); // Filter by level if provided
            $startDate = request('start_date');
            $endDate = request('end_date');
            
            $logPath = storage_path('logs/laravel.log');
            
            if (!file_exists($logPath)) {
                return generalResponse(
                    'Log file not found',
                    false,
                    [
                        'data' => [],
                        'totalData' => 0,
                        'currentPage' => $page,
                        'itemsPerPage' => $itemsPerPage,
                        'totalPages' => 0
                    ]
                );
            }
            
            // Calculate offset
            $offset = $page == 1 ? 0 : ($page - 1) * $itemsPerPage;
            
            $result = $this->parseLogFileWithPagination($logPath, $itemsPerPage, $offset, $level, $startDate, $endDate);
            
            return generalResponse(
                'success',
                false,
                [
                    'data' => $result['logs'],
                    'totalData' => $result['totalCount'],
                    'currentPage' => $page,
                    'itemsPerPage' => $itemsPerPage,
                    'totalPages' => ceil($result['totalCount'] / $itemsPerPage)
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Parse Laravel log file with pagination support
     * @param string $filePath
     * @param int $itemsPerPage
     * @param int $offset
     * @param string|null $levelFilter
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    private function parseLogFileWithPagination($filePath, $itemsPerPage = 100, $offset = 0, $levelFilter = null, $startDate = null, $endDate = null)
    {
        $allLogs = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return ['logs' => [], 'totalCount' => 0];
        }
        
        $currentLog = '';
        
        // Read all lines
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
        }
        fclose($handle);
        
        // Process logs in reverse order (latest first)
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            // Check if line starts with timestamp pattern [YYYY-MM-DD HH:MM:SS]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                // Process previous log if exists
                if ($currentLog) {
                    $parsedLog = $this->parseLogEntry($currentLog);
                    if ($parsedLog && $this->matchesFilters($parsedLog, $levelFilter, $startDate, $endDate)) {
                        $allLogs[] = $parsedLog;
                    }
                }
                
                // Start new log entry
                $currentLog = $line;
            } else {
                // Continuation of current log (stacktrace, etc.)
                $currentLog .= $line;
            }
        }
        
        // Process the last log entry
        if ($currentLog) {
            $parsedLog = $this->parseLogEntry($currentLog);
            if ($parsedLog && $this->matchesFilters($parsedLog, $levelFilter, $startDate, $endDate)) {
                $allLogs[] = $parsedLog;
            }
        }
        
        $totalCount = count($allLogs);
        
        // Apply pagination
        $paginatedLogs = array_slice($allLogs, $offset, $itemsPerPage);
        
        return [
            'logs' => $paginatedLogs,
            'totalCount' => $totalCount
        ];
    }

    /**
     * Parse Laravel log file and return formatted logs (legacy method for backward compatibility)
     * @param string $filePath
     * @param int $limit
     * @param string|null $levelFilter
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    private function parseLogFile($filePath, $limit = 100, $levelFilter = null, $startDate = null, $endDate = null)
    {
        $logs = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return [];
        }
        
        $currentLog = '';
        $logCount = 0;
        
        // Read file in reverse order to get latest logs first
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
        }
        fclose($handle);
        
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            // Check if line starts with timestamp pattern [YYYY-MM-DD HH:MM:SS]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                // Process previous log if exists
                if ($currentLog) {
                    $parsedLog = $this->parseLogEntry($currentLog);
                    if ($parsedLog && $this->matchesFilters($parsedLog, $levelFilter, $startDate, $endDate)) {
                        $logs[] = $parsedLog;
                        $logCount++;
                    }
                    
                    if ($logCount >= $limit) {
                        break;
                    }
                }
                
                // Start new log entry
                $currentLog = $line;
            } else {
                // Continuation of current log (stacktrace, etc.)
                $currentLog .= $line;
            }
        }
        
        // Process the last log entry
        if ($currentLog && $logCount < $limit) {
            $parsedLog = $this->parseLogEntry($currentLog);
            if ($parsedLog && $this->matchesFilters($parsedLog, $levelFilter, $startDate, $endDate)) {
                $logs[] = $parsedLog;
            }
        }
        
        return $logs;
    }

    /**
     * Parse individual log entry
     * @param string $logEntry
     * @return array|null
     */
    private function parseLogEntry($logEntry)
    {
        // Pattern: [timestamp] environment.level: message {"context"...}
        if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $logEntry, $matches)) {
            return null;
        }
        
        $timestamp = $matches[1];
        $environment = $matches[2];
        $level = strtolower($matches[3]);
        $messageAndContext = $matches[4];
        
        // Extract context JSON if present
        $context = null;
        $message = $messageAndContext;
        
        // Look for JSON context at the end of the message
        if (preg_match('/^(.+?) (\{.+\})\s*$/', $messageAndContext, $contextMatches)) {
            $message = trim($contextMatches[1]);
            try {
                $context = json_decode($contextMatches[2], true);
            } catch (\Exception $e) {
                // If JSON decode fails, keep context as null
            }
        }
        
        // Extract file and line information from message or stacktrace
        $file = null;
        $line = null;
        
        // Look for file path and line in the message or exception
        if (preg_match('/in (\/[^:]+):(\d+)/', $logEntry, $fileMatches)) {
            $file = $fileMatches[1];
            $line = (int) $fileMatches[2];
        } elseif (preg_match('/at (\/[^:]+):(\d+)/', $logEntry, $fileMatches)) {
            $file = $fileMatches[1];
            $line = (int) $fileMatches[2];
        }
        
        // Clean up message - remove quotes if they wrap the entire message
        $message = trim($message, '"\'');
        
        // Handle special cases for different log levels
        if ($level === 'error' && isset($context['exception'])) {
            // For errors with exceptions, try to extract more meaningful message
            if (preg_match('/^([^(]+)/', $message, $errorMatches)) {
                $message = trim($errorMatches[1]);
            }
        }
        
        return [
            'level' => $level,
            'timestamp' => date('c', strtotime($timestamp)), // Convert to ISO 8601 format
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $context,
            'exception' => isset($context['exception']) ? $context['exception'] : null
        ];
    }

    /**
     * Check if log entry matches the provided filters
     * @param array $log
     * @param string|null $levelFilter
     * @param string|null $startDate
     * @param string|null $endDate
     * @return bool
     */
    private function matchesFilters($log, $levelFilter, $startDate, $endDate)
    {
        // Filter by level
        if ($levelFilter && $log['level'] !== strtolower($levelFilter)) {
            return false;
        }
        
        // Filter by date range
        $logTimestamp = strtotime($log['timestamp']);
        
        if ($startDate && $logTimestamp < strtotime($startDate)) {
            return false;
        }
        
        if ($endDate && $logTimestamp > strtotime($endDate . ' 23:59:59')) {
            return false;
        }
        
        return true;
    }

    /**
     * Clear the log file
     */
    public function clearLogs(): array
    {
        try {
            $logPath = storage_path('logs/laravel.log');

            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }

            return generalResponse(
                'Logs cleared successfully',
                false,
                []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
