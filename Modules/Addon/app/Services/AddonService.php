<?php

namespace Modules\Addon\Services;

use App\Enums\ErrorCode\Code;
use App\Services\LocalNasService;
use App\Services\NasConnectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Addon\Repository\AddonRepository;

class AddonService {
    private $repo;

    private $historyRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new AddonRepository;

        $this->historyRepo = new \Modules\Addon\Repository\AddonUpdateHistoryRepository();
    }

    /**
     * Get all addons for selection
     *
     * @return array
     */
    public function getAll()
    {
        $data = $this->repo->list('id as value, name as title');

        return generalResponse(
            'success',
            false,
            $data->toArray(),
        );
    }

    /**
     * Send line message to developer based on request
     *
     * @param array $data
     * @return array
     */
    public function askDeveloper(array $data)
    {
        $line = new \Modules\LineMessaging\Services\LineConnectionService();

        $lineId = getSettingByKey('lineId');

        $message = "Topik pertanyaan: " . $data['topic'] . "\n";
        if ($data['topic'] == 'addon') {
            $addon = $this->repo->show((int) $data['addon'], 'id,name');
            $message .= "Addon: " . $addon->name . "\n";
        }
        $message .= "Pengirim: " . $data['name'] . "\n";
        $message .= "Pesan: " . $data['message'];

        $messages = [
            [
                'type' => 'text', 
                'text' => "Hello, Ada pertanyaan nih dari " . $data['name'] . "\n",
            ],
            [
                'type' => 'text',
                'text' => $message,
            ],
        ];

        $send = $line->sendMessage($messages, $lineId);

        return generalResponse(
            'Message has been sent to developer',
            false,
        );
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');;
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

            // dummy
            $paginated = collect($paginated)->map(function ($item) {
                $item['preview_img'] = env("APP_URL") . '/storage/addons/' . $item->preview_img;

                return $item;
            })->toArray();

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

    public function getUpdatedAddons()
    {
        $data = $this->historyRepo->list('id as uid,addon_id,updated_at', '', ['addon:id,name,preview_img'], 5, true);
        $data = collect($data)
            ->sortByDesc('updated_at')->values();

        return generalResponse(
            'Success',
            false,
            $data->toArray(),
        );
    }

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(int $uid): array
    {
        try {
            $data = $this->repo->show($uid)->toArray();

            $data['preview_img'] = isset($data['preview_img']) ? asset('storage/addons/' . $data['preview_img']) : null;

            return generalResponse(
                'success',
                false,
                $data,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function upgrades(array $data, int $uid)
    {
        DB::beginTransaction();
        try {
            $addon = $this->repo->show($uid);
            $slugName = str_replace(' ', '_', $addon->name);

            $nasService = new NasConnectionService();
            $sharedFolder = getSettingByKey('folder');

            // replace file
            $nasService->deleteFolder("{$sharedFolder}/{$addon->main_file}");

            $addonFileMime = $data['main_file']->getClientMimeType();
            $addonFile = uploadImage($data['main_file'], 'addons', true);
            $upload = $nasService->uploadFile(storage_path('app/public/addons/' . $addonFile), $addonFile, $addonFileMime, "{$sharedFolder}/" . $slugName);
            $mainFilePayload = $slugName . '/' . $addonFile;

            $tutorialVideoPayload = $addon->tutorial_video;
            if (!empty($data['tutorial_video'])) {
                $nasService->deleteFolder("{$sharedFolder}/{$addon->tutorial_video}");

                $tutorialVideoMime = $data['tutorial_video']->getClientMimeType();
                $tutorialVideoFile = uploadImage($data['tutorial_video'], 'addons', true);
                $uploadTutorialVideo = $nasService->uploadFile(storage_path('app/public/addons/' . $tutorialVideoFile), $tutorialVideoFile, $tutorialVideoMime, "{$sharedFolder}/" . $slugName);
                $tutorialVideoPayload = $slugName . '/' . $tutorialVideoFile;
            }

            $previewFile = $addon->preview_img;
            if (!empty($data['preview_image'])) {
                if (file_exists(storage_path('app/public/addons/' . $data['preview_image']))) {
                    unlink(storage_path('app/public/addons/' . $data['preview_image']));
                }

                $perviewFileMime = $data['preview_image']->getClientMimeType();
                $previewFile = uploadImage($data['preview_image'], 'addons', true);
                $nasService->uploadFile(storage_path('app/public/addons/' . $previewFile), $previewFile, $perviewFileMime, "{$sharedFolder}/" . $slugName);
            }

            $this->repo->update([
                'preview_img' => $previewFile,
                'tutorial_video' => $tutorialVideoPayload ?? 'tutorial',
                'main_file' => $mainFilePayload ?? 'main file',
            ], $uid);

            $this->historyRepo->store([
                'addon_id' => $uid,
                'improvements' => $data['improvements'],
            ]);

            DB::commit();

            return generalResponse(
                'Success Upgrade addon',
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
        Log::debug('store addon resource data: ', $data);
        try {
            $localService = new LocalNasService();
            $isConnect = $localService->checkConnection();
            
            // Check connection
            if (!$isConnect) {
                return errorResponse('Unable to connect to the server. recheck the configuration in the settings menu');
            }

            /**
             * Upload files to local,
             * Then upload to nas
             * Then delete files in local
             */
            if (!\Illuminate\Support\Facades\Storage::exists('app/public/addons')) {
                \Illuminate\Support\Facades\Storage::makeDirectory('app/public/addons');
            }

            $slugName = str_replace(' ', '_', $data['name']);

            $sharedFolder = getSettingByKey('folder'); // define shared folders

            $uploadMainAddon = \Illuminate\Support\Facades\Http::post(env('NAS_URL_LOCAL') . '/local/upload', [
                'file' => $data['addon_file'],
                'targetPath' => "{$sharedFolder}/" . $slugName,
            ]);

            return generalResponse(
                'success',
                false,
                json_decode($uploadMainAddon, true) ?? ['path' => env('NAS_URL_LOCAL') . '/local/upload'],
            );


            /**
             * create folder in the NAS
             */
            $nasService = new NasConnectionService();
            $init = $nasService->initAddonsFolder();
            Log::debug('init nas', [$init]);
            if ($init['error']) {
                return errorResponse($init['message']);
            }
            $slugName = str_replace(' ', '_', $data['name']);
            $folder = ['name' => $data['name'], 'path' => '/apitesting/' . $data['name']];

            // $create = $nasService->createNASFolder($folder['path'], $folder['name']);

            // if ($create['success'] == FALSE) {
            //     return errorResponse('Failed to create folder');
            // }

            if (!\Illuminate\Support\Facades\Storage::exists('app/public/addons')) {
                \Illuminate\Support\Facades\Storage::makeDirectory('app/public/addons');
            }

            $sharedFolder = getSettingByKey('folder');

            $addonFileMime = $data['addon_file']->getClientMimeType();
            $addonFile = uploadImage($data['addon_file'], 'addons', true);
            Log::debug('upload main file into local', [$addonFile]);
            $upload = $nasService->uploadFile(storage_path('app/public/addons/' . $addonFile), $addonFile, $addonFileMime, "{$sharedFolder}/" . $slugName);
            Log::debug('upload main file into nas', [$upload]);
            $mainFilePayload = $slugName . '/' . $addonFile;

            $tutorialVideoPayload = null;
            if (!empty($data['tutorial_video'])) {
                $tutorialVideoMime = $data['tutorial_video']->getClientMimeType();
                $tutorialVideoFile = uploadImage($data['tutorial_video'], 'addons', true);
                Log::debug('upload tutorial to local', [$tutorialVideoFile]);
                $uploadTutorialVideo = $nasService->uploadFile(storage_path('app/public/addons/' . $tutorialVideoFile), $tutorialVideoFile, $tutorialVideoMime, "{$sharedFolder}/" . $slugName);
                Log::debug('upload tutorial to nas', [$uploadTutorialVideo]);
                $tutorialVideoPayload = $slugName . '/' . $tutorialVideoFile;
            }

            $perviewFileMime = $data['preview_image']->getClientMimeType();
            $previewFile = uploadImage($data['preview_image'], 'addons', true);
            Log::debug('upload preview to local', [$previewFile]);
            $preview = $nasService->uploadFile(storage_path('app/public/addons/' . $previewFile), $previewFile, $perviewFileMime, "{$sharedFolder}/" . $slugName);
            Log::debug('upload preview to nas', [$preview]);

            if ($upload['success'] != FALSE) {
                if (file_exists(storage_path('app/public/addons/' . $addonFile))) {
                    unlink(storage_path('app/public/addons/' . $addonFile));
                }
            }
            
            if ($uploadTutorialVideo['success'] != FALSE) {
                if (file_exists(storage_path('app/public/addons/' . $tutorialVideoFile))) {
                    unlink(storage_path('app/public/addons/' . $tutorialVideoFile));
                }
            }

            $this->repo->store([
                'name' => $data['name'],
                'description' => $data['description'],
                'preview_img' => $previewFile ?? 'preview',
                'tutorial_video' => $tutorialVideoPayload ?? 'tutorial',
                'main_file' => $mainFilePayload ?? 'main file',
            ]);

            return generalResponse(
                __('global.successCreateAddon'),
                false,
            );
        } catch (\Throwable $th) {
            Log::debug('failed store addons', [
                'file' => $th->getFile(),
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);

            return errorResponse($th);
        }
    }

    public function validateConfiguration()
    {
        $service = new \App\Services\NasConnectionService();
        $response = $service->initAddonsFolder();

        return generalResponse(
            'success',
            false,
            $response
        );
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

    public function download(int $id, string $type)
    {
        try {
            $addon = $this->repo->show($id);
            $sharedFolder = getSettingByKey('folder');

            if ($type == 'main') {
                $path = "{$sharedFolder}/" . $addon->main_file;
            } else if ($type == 'tutorial') {
                $path = "{$sharedFolder}/" . $addon->tutorial_video;
            }

            $nasService = new NasConnectionService();
            $download = $nasService->download($path);

            return generalResponse(
                'success',
                false,
                $download
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
            $nasService = new NasConnectionService();
            $sharedFolder = getSettingByKey('folder');

            foreach ($ids as $id) {
                $addon = $this->repo->show($id);
                $slug = str_replace(' ', '_', $addon->name);

                // delete in NAS
                $delete = $nasService->deleteFolder("{$sharedFolder}/" . $slug);
                Log::debug('Delete folder nas: ', [$delete]);

                // delete file
                if (file_exists(storage_path('app/public/addons/' . $addon->preview_img))) {
                    unlink(storage_path('app/public/addons/' . $addon->preview_img));
                }
            }
        
            $this->repo->bulkDelete($ids, 'id');

            return generalResponse(
                'Success delete addon(s)',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}