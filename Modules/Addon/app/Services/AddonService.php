<?php

namespace Modules\Addon\Services;

use App\Enums\ErrorCode\Code;
use App\Services\LocalNasService;
use App\Services\NasConnectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Addon\Repository\AddonRepository;
use CURLFile;

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

    protected function uploadToLocalNas(
        $file,
        string $targetFolder
    )
    {
        $name = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();

        Log::debug('function uploadToLocalNas name: ', [$name]);
        Log::debug('function uploadToLocalNas mime: ', [$mime]);
        Log::debug('function uploadToLocalNas size: ', [$size]);

        $dummy = uploadFile('tmp', $file);

        Log::debug('dummy file', [$dummy]);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://bright-huge-gopher.ngrok-free.app/api/local/upload',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'targetPath' => $targetFolder,
                'filedata'=> new CURLFILE(storage_path('app/public/tmp/' . $dummy))),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
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
            $sharedFolder = getSettingByKey('folder');
            $username = getSettingByKey('user');
            $password = getSettingByKey('password');
            $url = getSettingByKey('server');

            $slugName = str_replace(' ', '_', $data['name']);

            $targetPath = $sharedFolder . '/' . $slugName;

            // upload file in local
            $mime = $data['preview_image']->getClientMimeType();
            $ext = $data['preview_image']->getClientOriginalExtension();
            Log::debug('addon ext: ', [$ext]);
            $datetime = date('YmdHis');
            $name = "uploaded_file_{$datetime}.{$ext}";
            $previewImage = Storage::putFileAs('addons', $data['preview_image'], $name);
            $path = storage_path('app/public/addons/' . $name);

            $payload = [
                'path' => $targetPath,
                'create_parents' => 'true',
                'mtime' => '',
                'overwrite' => 'true',
                'filename'=> new CURLFile($path, $mime, $name),
            ];

            $response = curlRequest(env('NAS_URL_LOCAL') . '/local/upload', $payload);

            return generalResponse(
                'success',
                false,
                $response,
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