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
        if (($message instanceof Throwable) && App::environment('local')) {
            $out = "Error: " . $message->getMessage() . ', at line ' . $message->getLine() . '. Check file ' . $message->getFile();
        } else if (($message instanceof Throwable) && !App::environment('local')) {
            $out = __('global.failedProcessingData');
        } else if (!$message instanceof Throwable) {
            $out = $message;
        }

        $class = "UserNotFound";
        
        if (file_exists(base_path('exceptions.json'))) {
            $exceptions = File::get(base_path('exceptions.json'));
            $exceptionArray = json_decode($exceptions, true);
            $arrayKeys = array_keys($exceptionArray);
            
            foreach ($arrayKeys as $exception) {
                $check = "\\App\\Exceptions\\{$exception}";
                if ($message instanceof $check) {
                    $out = $message->getMessage();
                    break;
                }{}
            }
        }

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