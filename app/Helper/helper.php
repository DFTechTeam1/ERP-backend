<?php

use App\Enums\ErrorCode\Code;
use App\Enums\System\BaseRole;
use App\Exceptions\UserNotFound;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;
use Modules\Hrd\Models\Employee;
use Modules\Telegram\Models\TelegramSession;
use SimpleSoftwareIO\QrCode\Facades\QrCode as FacadesQrCode;

if (!function_exists('isLocal')) {
    function isLocal()
    {
        return App::environment('local') && config('app.url') == 'https://backend.test';
    }
}

if (!function_exists('setEmailConfiguration')) {
    function setEmailConfiguration()
    {
        config([
            'mail.mailers.smtp.host' => getSettingByKey('email_host'),
            'mail.mailers.smtp.port' => getSettingByKey('email_port'),
            'mail.mailers.smtp.username' => getSettingByKey('username'),
            'mail.mailers.smtp.password' => getSettingByKey('password'),
            'mail.from.address' => getSettingByKey('sender_email'),
            'mail.from.name' => getSettingByKey('sender_name'),
            'mail.default' => 'smtp',
            'mail.mailers.smtp.encryption' => 'tls',
        ]);
    }
}

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
        $arr = ['App\Exceptions\TemplateNotValid'];

        if ($message instanceof Throwable) {
            logging('error: ', [$message]);
            $files = scandir(app_path('Exceptions'));

            $outputFiles = [];
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $name = explode('.php', $file);

                    $path = "App\Exceptions\\" . $name[0];
                    $outputFiles[] = $path;
                }
            }

            if (in_array(get_class($message), $outputFiles)) {
                $out = $message->getMessage();
            } else {
                if (config('app.env') == 'local' || config('app.env') == 'testing') {
                    $out = "Error: " . $message->getMessage() . ', at line ' . $message->getLine() . '. Check file ' . $message->getFile();
                    $messageError = $out;
                } else {
                    $out = __('global.failedProcessingData');
                }
            }
        } else if (($message instanceof Throwable) && config('app.env') == 'local') {
            logging('error: ', [$message]);
            $out = "Error: " . $message->getMessage() . ', at line ' . $message->getLine() . '. Check file ' . $message->getFile();
        } else if (($message instanceof Throwable) && config('app.env') != 'local') {
            logging('error: ', [$message]);
            $out = __('global.failedProcessingData');
        } else if (!$message instanceof Throwable) {
            logging('error: ', [$message]);
            $out = $message;
        }

        // if (file_exists(base_path('exceptions.json'))) {
        //     $exceptions = File::get(base_path('exceptions.json'));
        //     $exceptionArray = json_decode($exceptions, true);
        //     $arrayKeys = array_keys($exceptionArray);


        //     foreach ($arrayKeys as $exception) {
        //         $check = "\\App\\Exceptions\\{$exception}";
        //         if ($message instanceof $check) {
        //             $out = $message->getMessage();
        //             break;
        //         }
        //     }
        // }

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

if (!function_exists('generateQrcode')) {
    function generateQrcode($payload, string $filename) {
        $explode = explode('/', $filename);

        array_pop($explode);

        $path = implode('/', $explode);

         if (!is_dir(storage_path("app/public/{$path}"))) {
             mkdir(storage_path("app/public/{$path}"), 0777, true);
         }
//        \Illuminate\Support\Facades\Storage::makeDirectory($path);

        $fullpath = storage_path('app/public/' . $filename);

        $from = [255, 0, 0];
        $to = [0, 0, 255];

        FacadesQrCode::format('png')
            ->size(512)
            ->style('round')
            ->eye('square')
            ->gradient($from[0], $from[1], $from[2], $to[0], $to[1], $to[2], 'diagonal')
            ->margin(1)
            ->generate($payload, $fullpath);

        return $filename;
    }
}

if (!function_exists('getIdFromUid')) {
    function getIdFromUid(string $uid, $model)
    {
        $data = $model->select('id')
            ->where("uid", $uid)
            ->first();

        return $data ? $data->id : 0;
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
        try {
            $path = storage_path("app/public/{$path}");

            $ext = $image->getClientOriginalExtension();
            $originalName = 'image';
            $datetime = strtotime('now') . random_int(1,8);

            $name = "{$originalName}_{$datetime}.{$extTarget}";

            // create file
            if (!is_dir($path)) {
                File::makeDirectory($path, 0777, true);
            }

            $filepath = $path . '/' . $name;

    //        Image::read($image)->toWebp($compressValue)->save($filepath);

            $imageManager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
            $newImage = $imageManager->read($image);
            $newImage->scale(height: 400);
            $newImage->toWebp(60);
            $newImage->save($filepath);

            return $name;
        } catch (\Throwable $th) {
            return false;
        }
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
    function generateRandomPassword($length = 10)
    {
        $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charLen = strlen($char);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $char[random_int(0, $charLen - 1)];
        }
        return $password;
    }
}

if (!function_exists('generateRandomSymbol')) {
    function generateRandomSymbol()
    {
        $length = 1;
        $char = '@$%&*()!';
        $charLen = strlen($char);
        $symbol = '';
        for ($i = 0; $i < $length; $i++) {
            $symbol .= $char[random_int(0, $charLen - 1)];
        }
        return $symbol;
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

        $employees = [];
        foreach ($users as $user) {
            $permissions = $user->getPermissionsViaRoles();
            $permissionNames = collect($permissions)->pluck('name')->toArray();
            if (in_array('accept_request_equipment', $permissionNames)) {
                $employees[] = \Modules\Hrd\Models\Employee::selectRaw('id,uid,name,line_id,telegram_chat_id,user_id')
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

        return $out;
    }
}

if (!function_exists('isHrdRole')) {
    function isHrdRole() {
        $role = \Illuminate\Support\Facades\DB::table("roles")
            ->whereRaw("lower(name) = 'hrd'")
            ->first();

        $output = false;
        if ($role) {
            $userRoles = auth()->user()->roles;

            foreach ($userRoles as $roleData) {
                if ($roleData->id == $role->id) {
                    $output = true;
                    break;
                }
            }
        }

        return $output;
    }
}

if (!function_exists('isProjectPIC')) {
    function isProjectPIC($projectId, int $employeeId) {
        if (gettype($projectId) == 'string') {
            $projectId = getIdFromUid($projectId, new \Modules\Production\Models\Project());
        }

        $projectData = \Modules\Production\Models\ProjectPersonInCharge::select('id')
            ->where('project_id', $projectId)
            ->where('pic_id', $employeeId)
            ->first();

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
        return auth()->user()->hasRole(BaseRole::Director->value);
    }
}

if (!function_exists('isItSupport')) {
    function isItSupport() {
        return auth()->user()->hasRole(BaseRole::ItSupport->value);
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

if (!function_exists('parseUserAgent')) {
    function parseUserAgent($userAgent) {
        $browser = 'Unknown';
        $os = 'Unknown';

        // Detect browser
        if (!App::runningInConsole()) {
            if (strpos($userAgent, 'Firefox') !== false) {
                $browser = 'Firefox';
            } elseif (strpos($userAgent, 'Chrome') !== false) {
                $browser = 'Chrome';
            } elseif (strpos($userAgent, 'Safari') !== false) {
                $browser = 'Safari';
            } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
                $browser = 'Internet Explorer';
            }

            // Detect OS
            if (strpos($userAgent, 'Windows NT') !== false) {
                $os = 'Windows';
            } elseif (strpos($userAgent, 'Mac OS X') !== false) {
                $os = 'Mac OS';
            } elseif (strpos($userAgent, 'Linux') !== false) {
                $os = 'Linux';
            } elseif (strpos($userAgent, 'Android') !== false) {
                $os = 'Android';
            } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
                $os = 'iOS';
            }
        }

        return ['browser' => $browser, 'os' => $os];
    }
}

if (!function_exists('getUserAgentInfo')) {
    function getUserAgentInfo() {
        return App::runningInConsole() ? '' : $_SERVER['HTTP_USER_AGENT'];
    }
}

if (!function_exists('getClientIp')) {
    function getClientIp() {

        $ip = '';
        if (!App::runningInConsole()) {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                // Check for IP from shared internet
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Check for IP from a proxy
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                // Fallback to REMOTE_ADDR
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }
        return $ip;
    }
}

if (!function_exists('getLengthOfService')) {
    function getLengthOfService(string $startDate)
    {
        $start = new DateTime($startDate);
        $now = new DateTime('now');

        $diff = date_diff($now, $start);

        $year = $diff->y;
        $month = $diff->m;
        $day = $diff->d;

        $text = $day . ' ' . __("global.day");
        if ($month != 0) {
            $text = $month . ' ' . __('global.month') . ' ' . $text;
        }

        if ($year != 0) {
            $text = $year . ' ' . __("global.year") . ' ' . $text;
        }

        return $text;
    }
}

if (!function_exists('removeDuplicateArray')) {
    function removeDuplicateArray(array $arr) {
        $serialized = array_map('serialize', $arr);

        $unique = array_unique($serialized);

        $unserialize = array_map('unserialize', $unique);

        return $unserialize;
    }
}

if (!function_exists('isAssistantPMRole')) {
    function isAssistantPMRole() {
        $user = auth()->user();

        $out = false;
        if ($user->hasRole('assistant manager')) {
            $out = true;
        }

        return $out;
    }
}

if (!function_exists('formatSearchConditions')) {
    function formatSearchConditions(array $filters, string $where) {
        if (empty($where)) {
            $where = '';
        }

        foreach ($filters as $data) {
            $value = $data['value'];

            if (gettype($data['value']) == 'string') {
                $value = strtolower($data['value']);
            }

            if ($data['condition'] == 'contain') {
                if (gettype($value) == 'array') {
                    $condition = " in ";
                    $valueString = implode(',', $value);
                    $value = "({$valueString})";
                } else {
                    $condition = " like ";
                    $value = "'%{$value}%'";
                }

            } else if ($data['condition'] == 'not_contain') {
                $condition = ' not like ';
                $value = "'%{$value}%'";
            } else if ($data['condition'] == 'equal') {
                $condition = ' = ';
            } else if ($data['condition'] == 'not_equal') {
                $condition = ' != ';
            } else if ($data['condition'] == 'more_than') {
                $condition = " >= ";
            }

            $where .= $data['field'] . $condition . $value . ' and ';
        }
        $where = rtrim($where, " and");

        return $where;
    }
}

if (!function_exists('uploadBase64')) {
    function uploadBase64(string $base64Image, string $path)
    {
        // Decode the base64 string
        $imageParts = explode(";base64,", $base64Image);
        if (count($imageParts) != 2) {
            return null;
        }

        $imageTypeAux = explode("image/", $imageParts[0]);
        if (count($imageTypeAux) != 2) {
            return null;
        }

        $imageType = $imageTypeAux[1]; // e.g., png, jpg, etc.
        $imageBase64 = base64_decode($imageParts[1]);

        // Create a unique file name
        $fileName = uniqid() . '.' . $imageType;

        // Define the storage path
        $filePath = $path . '/' . $fileName;

        // Save the image using Laravel's Storage facade
        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $imageBase64);

        return $fileName;
    }
}

if (!function_exists('generateBarcode')) {
    function generateBarcode(string $code, string $path)
    {
        $realPath = storage_path('app/public/' . $path);
        $service = new \Milon\Barcode\DNS1D();
        if (!is_dir($realPath)) {
            mkdir($realPath, 0777, true);
        }
        $service->setStorPath($realPath);

        $barcode = $service->getBarcodePNGPath($code, 'PDF417');
        if (!$barcode) {
            return null;
        }

        return str_replace(storage_path('app/public/'), '', $barcode);
    }
}


if (!function_exists('getStructureNasFolder')) {
    function getStructureNasFolder(): array {
        return [
            "{year}/{format_name}/Brief",
            "{year}/{format_name}/Asset_3D",
            "{year}/{format_name}/Asset_Footage",
            "{year}/{format_name}/Asset_Render",
            "{year}/{format_name}/Final_Render",
            "{year}/{format_name}/Aseet_Sementara",
            "{year}/{format_name}/Preview",
            "{year}/{format_name}/Sketsa",
            "{year}/{format_name}/TC",
            "{year}/{format_name}/Raw",
            "{year}/{format_name}/Audio",
        ];
    }
}

if (!function_exists('MonthInBahasa')) {
    function MonthInBahasa($search = ''): array|string {
        $months = ['Januari', 'Febuari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        if (!empty($search)) {
            $explode = str_split($search);
            if ($explode[0] == 0) {
                $search = $explode[1];
            }

            $months = $months[$search -1];
        }

        return $months;
    }
}

if (!function_exists('stringToPascalSnakeCase')) {
    function stringToPascalSnakeCase($string) {
        // Remove special characters except spaces and apostrophes
        $string = preg_replace('/[^a-zA-Z0-9\s\']/', '', $string);

        // Convert to PascalCase (capitalize each word)
        $pascalCase = str_replace(' ', '', ucwords(strtolower($string)));

        // Convert PascalCase to snake_case while keeping capitalization on each word
        $snakeCase = preg_replace('/([a-z])([A-Z])/', '$1_$2', $pascalCase);

        return strtoupper($snakeCase);
    }
}

if (!function_exists('checkForeignKey')) {
    function checkForeignKey($tableName, $columnName)
    {
        return \Illuminate\Support\Facades\DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
            ->exists();

    }
}

if (!function_exists('putTelegramSession')) {
    function putTelegramSession(string $chatId, mixed $value)
    {
        $check = \Modules\Telegram\Models\TelegramSession::select('*')
            ->where('chat_id', $chatId)
            ->active()
            ->first();

        // deactive current session
        if ($check) {
            $check->status = 0;
            $check->save();
        }

        // create new session
        \Modules\Telegram\Models\TelegramSession::create([
            'chat_id' => $chatId,
            'value' => $value,
            'status' => 1
        ]);
    }
}

if (!function_exists('getTelegramSession')) {
    function getTelegramSession(string $chatId)
    {
        $data = TelegramSession::select('*')
            ->where('chat_id', $chatId)
            ->active()
            ->first();

        return $data ? $data->value : null;
    }
}

if (!function_exists('destroyTelegramSession')) {
    function destroyTelegramSession(string $chatId, string $value)
    {
        TelegramSession::where('chat_id', $chatId)
            ->where('value', $value)->delete();
    }
}

if (!function_exists('loggingProject')) {
    function loggingProject(mixed $projectId, string $message) {

    }
}

if (!function_exists('generateRandomColor')) {
    function generateRandomColor(string $email) {
        $hash = md5($email);

        return '#' . substr($hash, 0, 6);
    }
}

/**
 * Define authorized user is has a super power or not
 *
 * @param int $projectId
 * @return boolean
 */
if (!function_exists('hasSuperPower')) {
    function hasSuperPower(int $projectId): bool {
        $user = auth()->user();
        $employeeId = $user->employee_id;
        $isProjectPic = isProjectPIC($projectId, $employeeId);
        $isDirector = isDirector();

        return $isDirector || $isProjectPic || $user->hasRole(BaseRole::Root->value) ? true : false;
    }
}

/**
 * Define user have just LITTLE POWER or not
 * This LITTLE POWER IS SAME WITH LEAD MODELER POSITION
 *
 * @param object $taskPics
 * @return boolean
 */
if (!function_exists('hasLittlePower')) {
    function hasLittlePower(object $task): bool {
        $taskPics = $task['pics'];

        $user = auth()->user();
        $leadModeller = getSettingByKey('lead_3d_modeller');
        $leadModeller = getIdFromUid($leadModeller, new Employee());

        $output = (bool) ($leadModeller) &&
        (
            in_array($leadModeller, collect($taskPics)->pluck('employee_id')->toArray()) &&
            $leadModeller == $user->employee_id
        );

        if ($user->employee_id == $leadModeller && $task['is_modeler_task']) $output = true;

        return $output;
    }
}
