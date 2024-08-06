<?php

use App\Enums\ErrorCode\Code;
use App\Exceptions\UserNotFound;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;

if (!function_exists('successResponse')) {
    function generalResponse(
        string $message,
        $error = false,
        array $data = [],
        int $code = 201
    ): array
    {
        return [
            'error' => $error,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ];
    }
}

if (!function_exists('errorMessage')) {
    function errorMessage($message) {

        if (($message instanceof Throwable) && config('app.env') == 'local') {
            $out = "Error: " . $message->getMessage() . ', at line ' . $message->getLine() . '. Check file ' . $message->getFile();
        } else if (($message instanceof Throwable) && config('app.env') != 'local') {
            $out = __('global.failedProcessingData');
        } else if (!$message instanceof Throwable) {
            $out = $message;
        }
        
        if (file_exists(base_path('exceptions.json'))) {
            $exceptions = File::get(base_path('exceptions.json'));
            $exceptionArray = json_decode($exceptions, true);
            $arrayKeys = array_keys($exceptionArray);

            
            foreach ($arrayKeys as $exception) {
                $check = "\\App\\Exceptions\\{$exception}";
                if ($message instanceof $check) {
                    $out = $message->getMessage();
                    break;
                }
            }
        }

        logging('error processing: ', [$out]);

        return $out;

    }
}

if (!function_exists('apiResponse')) {
    function apiResponse(array $payload): JsonResponse
    {
        if ($payload['code'] == 422) {
            return response()->json([
                'message' => $payload['message'],
                'errors' => isset($payload['data']) ? $payload['data'] : [],
            ], $payload['code']);
        } else {
            return response()->json([
                'message' => $payload['message'],
                'data' => isset($payload['data']) ? $payload['data'] : [],
            ], $payload['code']);
        }
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse($message, array $data = [], $code = null)
    {
        $code = !$code ? Code::BadRequest->value : $code;

        return generalResponse(
            errorMessage($message),
            true,
            $data,
            $code,
        );
    }
}

if (!function_exists('createQr')) {
    function createQr($payload)
    {   
        $option = new QROptions;
        $option->version      = 7;
        // $option->outputBase64 = false;

        $qrcode = (new QRCode($option))->render($payload);

        return $qrcode;
    }
}

if (!function_exists('getIdFromUid')) {
    function getIdFromUid(string $uid, $model)
    {
        $data = $model->select('id')
            ->where("uid", $uid)
            ->first();

        return $data->id;
    }
}

if (!function_exists('getSlug')) {
    function getSlug(string $param)
    {
        $slugString = preg_replace('/[^a-zA-Z0-9\']/', '', $param);
        $slugString = str_replace("'", '', $slugString);
        $slugExplode = str_split($slugString);

        $slug = count($slugExplode) > 2 ? $slugExplode[0] . $slugExplode[1] . $slugExplode[2] : $slugExplode[0] . $slugExplode[1];

        return strtolower($slug);
    }
}

if (!function_exists('uploadFile')) {
    function uploadFile(string $path, $file) {
        try {
            $ext = $file->getClientOriginalExtension();
            $datetime = date('YmdHis');
            $name = "uploaded_file_{$datetime}.{$ext}";
    
            Storage::putFileAs($path, $file, $name);
    
            return $name;
        } catch (\Throwable $th) {
            Log::debug('uploadFile Error', [
                'file' => $th->getFile(),
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);
        }
    }
}

if (!function_exists('uploadAddon')) {
    function uploadAddon($file) {
        try {
            $mime = $file->getClientMimeType();
            Log::debug('mime in uploadAddon function: ', [$mime]);
    
            // if (
            //     $mime == 'image/png' ||
            //     $mime == 'image/jpg' ||
            //     $mime == 'image/jpeg' ||
            //     $mime == 'image/webp'
            // ) {
            //     $uploadedFile = uploadImageandCompress(
            //         'addons',
            //         10,
            //         $file
            //     );
            // } else {
            // }
            $uploadedFile = uploadFile(
                'addons',
                $file
            );
    
            return [
                'mime' => $mime,
                'file' => $uploadedFile,
            ];
        } catch (\Throwable $th) {
            Log::debug('uploadAddon Error', [
                'file' => $th->getFile(),
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);
        }
    }
}

if (!function_exists('uploadImage')) {
    function uploadImage(
        $image,
        string $folderName,
        bool $isOriginalName = false
    )
    {
        // sanitize
        $folderName = strtolower(implode('_', explode(' ', $folderName)));

        $ext = $image->getClientOriginalExtension();
        $datetime = date('YmdHis');
        
        $name = "uploaded_{$folderName}_{$datetime}.{$ext}";

        if ($isOriginalName) {
            $name = $image->getClientOriginalName();
        }

        // save file to storage
        if (Storage::putFileAs($folderName, $image, $name)) {
            return $name;
        }

        return null;
    }
}

if (!function_exists('uploadImageandCompress')) {
    function uploadImageandCompress(
        string $path,
        int $compressValue,
        $image,
        string $extTarget = 'webp',
    ) {
        $path = storage_path("app/public/{$path}");

        $ext = $image->getClientOriginalExtension();
        $originalName = $image->getClientOriginalName();
        $datetime = date('YmdHis');
        
        $name = "{$originalName}_{$datetime}.{$extTarget}";

        // create file
        if (!is_dir($path)) {
            File::makeDirectory($path, 0777, true);
        }

        $filepath = $path . '/' . $name;

        Image::read($image)->toWebp($compressValue)->save($filepath);

        return $name;
    }
}

if (!function_exists('deleteImage')) {
    function deleteImage($path) {
        if (File::exists($path)) {
            unlink($path);
        }
    }
}

if (!function_exists('deleteFolder')) {
    function deleteFolder(string $path)
    {
        if (is_dir($path)) {
            $files = glob($path . '/*', GLOB_MARK);
            if (count($files) > 0) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            rmdir($path);
        }
    }
}

if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword()
    {
        $length = 10;
        $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charLen = strlen($char);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $char[random_int(0, $charLen - 1)];
        }
        return $password;
    }
}

if (!function_exists('getSetting')) {
    function getSetting($code = '') {
        $data = \Illuminate\Support\Facades\Cache::get('setting');

        $out = $data;
        if (!empty($code)) {
            $out = collect($data)->where('code', $code)->toArray();
        }

        return $out;
    }
}

if (!function_exists('getSettingByKey')) {
    function getSettingByKey($key) {
        $data = \Illuminate\Support\Facades\Cache::get('setting');

        $data = collect($data)->where('key', $key)->values();

        return count($data) > 0 ? $data[0]['value'] : null;
    }
}

if (!function_exists('cachingSetting')) {
    function cachingSetting() {
        $setting = Cache::get('setting');
    
        if (!$setting) {
            Cache::rememberForever('setting', function () {
                $data = \Modules\Company\Models\Setting::get();

                return $data->toArray();
            });
        }
    }
}

if (!function_exists('storeCache')) {
    function storeCache(string $key, $value, $ttl = 60 * 60 * 6) {
        Cache::put($key, $value, $ttl);
    }
}

if (!function_exists('clearCache')) {
    function clearCache(string $cacheId) {
        Cache::forget($cacheId);
    }
}

if (!function_exists('getCache')) {
    function getCache(string $cacheId) {
        return Cache::get($cacheId);
    }
}

if (!function_exists('curlRequest')) {
    function curlRequest(string $url, array $payload)
    {
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['Access-Control-Allow-Origin' => '*',],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => $payload,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }
}

if (!function_exists('logging')) {
    function logging($key, array $value) {
        \Illuminate\Support\Facades\Log::debug($key, $value);
    }
}

if (!function_exists('getUserByRole')) {
    function getUserByRole(string $roleName) {
        $users = \App\Models\User::whereHas('roles', function (\Illuminate\Database\Eloquent\Builder $query) use ($roleName) {
            $query->whereRaw("LOWER(name) = '" . $roleName . "'");
        })->get();

        return $users;
    }
}

if (!function_exists('getPicOfInventory')) {
    function getPicOfInventory() {
        $users = getUserByRole('it support');
        // check permission
        logging('user data: ', $users->toArray());
        
        $employees = [];
        foreach ($users as $user) {
            $permissions = $user->getPermissionsViaRoles();
            $permissionNames = collect($permissions)->pluck('name')->toArray();
            logging('permissions data: ', $permissionNames);
            if (in_array('request_inventory', $permissionNames)) {
                logging('is have permission: ', [$user]);
                $employees[] = \Modules\Hrd\Models\Employee::selectRaw('id,uid,name,line_id,user_id')
                    ->where('user_id', $user->id)
                    ->first();
            }
        }

        return $employees;
    }
}

if (!function_exists('isSuperUserRole')) {
    function isSuperUserRole() {
        $role = getSettingByKey('super_user_role');

        $userRoles = auth()->user()->roles;
        $out = false;
        foreach ($userRoles as $userRole) {
            if ($userRole->id == $role) {
                $out = true;
            }
        }

        logging('isSuperUserRole', [$out]);

        return $out;
    }
}

if (!function_exists('isProjectPIC')) {
    function isProjectPIC($projectId, int $employeeId) {
        if (gettype($projectId) == 'string') {
            $projectId = getIdFromUid($projectId, new \Modules\Production\Models\Project());
        }

        logging('isPro pid', [$projectId]);
        logging('isPro eid', [$employeeId]);

        $projectData = \Modules\Production\Models\ProjectPersonInCharge::select('id')
            ->where('project_id', $projectId)
            ->where('pic_id', $employeeId)
            ->first();

        logging('isProjectPIC', [$projectData ? true : false]);

        return $projectData ? true : false;
    }
}

if (!function_exists('isEmployee')) {
    function isEmployee() {
        $user = auth()->user();

        return $user->is_employee;
    }
}

if (!function_exists('isDirector')) {
    /**
     * Function to check logged user as a director or not
     * THis check by employee position
     *
     * @return boolean
     */
    function isDirector() {
        $directorPosition = json_decode(getSettingByKey('position_as_directors'), true);

        $out = false;
        if ($directorPosition && !isSuperUserRole()) {
            $directorPosition = collect($directorPosition)->map(function ($item) {
                return getidFromUid($item, new \Modules\Company\Models\Position());
            })->toArray();

            $user = auth()->user();
            $employee = \Modules\Hrd\Models\Employee::selectRaw('id,position_id')
                ->find($user->employee_id);

            $out = in_array($employee->position_id, $directorPosition) ? true : false;
        }

        logging('isDirector', [$out]);

        return $out;
    }
}

if (!function_exists('snakeToCamel')) {
    function snakeToCamel(string $word) {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $word))));
    }
}

if (!function_exists('generateSequenceNumber')) {
    function generateSequenceNumber($number, $length = 4) {
        return str_pad($number, $length, 0, STR_PAD_LEFT);
    }
}

if (!function_exists('formatNotifications')) {
    function formatNotifications(array $payload)
    {
        $output = [];
        foreach ($payload as $notification) {
            if (!$notification['read_at']) {
                $output[] = [
                    'id' => $notification['id'],
                    'message' => $notification['data']['message'],
                    'title' => $notification['data']['title'],
                    'time' => date('d F Y, H:i', strtotime($notification['created_at'])),
                    'link' => $notification['data']['link'] ?? null,
                ];
            }
        }

        return $output;
    }
}