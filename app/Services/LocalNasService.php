<?php

namespace App\Services;

use CURLFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LocalNasService {
    /**
     * Username of NAS
     *
     * @var string
     */
    private $username;
    
    /**
     * Define password of NAS
     *
     * @var string
     */
    private $password;

    /**
     * Define shared folder nas
     *
     * @var sting
     */
    private $mainPath;

    /**
     * Define NAS url
     *
     * @var string
     */
    private $url;

    private $_sid;

    private $fullUrl;

    protected function createUrl($type = 'filestation')
    {
        if ($type == 'filestation') {
            $this->fullUrl = 'http://' . $this->url . ':5000/webapi/entry.cgi';
        } else if ($type == 'auth') {
            $this->fullUrl = 'http://' . $this->url . ':5000/webapi/auth.cgi';
        }
    }

    protected function login()
    {
        if (!Cache::get('NAS_SID')) {
            $this->createUrl('auth');

            $response = Http::get($this->fullUrl, [
                'api' => 'SYNO.API.Auth',
                'version' => '3',
                'method' => 'login',
                'account' => $this->username,
                'passwd' => $this->password,
                'session' => 'FileStation',
                'format' => 'sid',
            ]);

            $out = json_decode($response->body(), true);

            if ($out['success']) {
                $ttl = 60 * 60 * 4;
                Cache::set('NAS_SID', $out['data']['sid'], $ttl);
            }

            Log::debug('LOGIN NAS RESPONSE: ', $out);
        }
    }

    public function __construct()
    {
        if (Cache::get('setting')) {
            $this->username = getSettingByKey('user');
            $this->password = getSettingByKey('password');
            $this->url = getSettingByKey('server');
            $this->mainPath = getSettingByKey('folder');
            $this->_sid = getSettingByKey('nas_sid');
        }

        // authenticate
        $this->login();
    }

    public function getSharedFolders()
    {
        $this->createUrl('filestation');
        
        $param = [
            '_sid' => Cache::get('NAS_SID'),
            'api' => 'SYNO.FileStation.List',
            'version' => '2',
            'method' => 'list_share',
        ];
        
        $response = Http::get($this->fullUrl, $param);

        $param['url'] = $this->fullUrl;
        Log::debug('GET SHARED FOLDERS PARAM', $param);

        Log::debug('SHARED FOLDER RES: ', [$response->json()]);

        return $response->json();
    }

    public function uploadFile($file, string $targetPath)
    {
        try {
            /**
            * Upload files to local,
            * Then upload to nas
            * Then delete files in local
            */
            if (!\Illuminate\Support\Facades\Storage::exists('addons')) {
                \Illuminate\Support\Facades\Storage::makeDirectory('addons');
            }
           
            $mime = $file->getClientMimeType();
            $ext = $file->getClientOriginalExtension();
            Log::debug('addon ext: ', [$ext]);
            $datetime = date('YmdHis');
            $name = "uploaded_file_{$datetime}.{$ext}";

            $mainAddon = Storage::putFile('addons', $file);
            Log::debug('main addon upload res: ', [$mainAddon]);
    
            $path = storage_path('app/public/addons/' . $name);
    
            $this->createUrl('filestation');
    
            $curl = curl_init();
    
            $sid = Cache::get('NAS_SID');
    
            $this->fullUrl .= "?api=SYNO.FileStation.Upload&version=2&method=upload&_sid={$sid}";

            Log::debug('URL UPLOAD FILE TO NAS: ', [$this->fullUrl]);
    
            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->fullUrl,
            CURLOPT_HTTPHEADER => ['Access-Control-Allow-Origin' => '*',],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => array(
                'path' => $targetPath,
                'create_parents' => 'true',
                'mtime' => '',
                'overwrite' => 'true',
                'filename'=> new CURLFile($path, $mime, $name)),
            ));
    
            $response = curl_exec($curl);
    
            curl_close($curl);

            $finalResponse = json_decode($response, true);

            Log::debug('result response upload to nas: ', [
                'finalResponse' => $finalResponse
            ]);

            if ($finalResponse['success']) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
    
            return json_decode($response, true);
        } catch (\Throwable $th) {
            Log::debug('error nasService upload file: ', [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);

            return [
                'error' => true,
                'message' => $th->getMessage(),
                'data' => [
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                ],
            ];
        }
    }

    /**
     * Check connection to server
     *
     * @return void
     */
    public function checkConnection()
    {
        try {
            $folder = $this->getSharedFolders();
    
            return $folder['success'];
        } catch (\Throwable $th) {
            Log::debug('Error check connection: ', [
                'file' => $th->getFile(),
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);

            return false;
        }
    }
}