<?php

namespace App\Services;

use CURLFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NasConnectionService {
    private $url;

    private $config;

    private $sid;

    public function __construct()
    {
        $this->config = $this->addonConfiguration();

        // login
        $this->login();
    }

    protected function login()
    {
        $data = Cache::get('nas_addon_sid');
        if (!$data) {
            Cache::rememberForever('nas_addon_sid', function () {
                $this->createUrl('login');
                $login = Http::get($this->url, [
                    'api' => 'SYNO.API.Auth',
                    'version' => '3',
                    'method' => 'login',
                    'account' => $this->config['data']['user'],
                    'passwd' => $this->config['data']['password'],
                    'session' => 'FileStation',
                    'format' => 'sid',
                ]);
        
                $login = json_decode($login->body(), true);

                if ($login['success'] != FALSE) {
                    return $login['data']['sid'];
                }
            });
        }

        $this->sid = Cache::get('nas_addon_sid');
    }
    
    public function createUrl(string $type)
    {
        $this->url = 'http://' . $this->config['data']['server'] . ':5000/webapi';
        if ($type == 'fileStations') {
            $this->url .= '/entry.cgi';
        } else if ($type == 'login') {
            $this->url .= '/auth.cgi';
        }
    }

    public function folderList()
    {
        $this->createUrl('fileStations');

        $res = Http::get('http://192.168.100.105:5000/webapi/entry.cgi?api=SYNO.FileStation.List&version=2&method=list_share&_sid=jAbvuv-wUGdTzyek2OX9dH9_IEOYH967BGbXFI9sd1k1UViWLljPUapqsBgiRXss08Q7uGdQk-ngs1irT5nJRg');

        return json_decode($res->body(), true);
    }

    public function addonConfiguration()
    {
        $data = getSetting('addon');
        [$folder, $server, $user, $password] = '';

        foreach ($data as $d) {
            if ($d['key'] == 'folder') {
                $folder = $d['value'];
            }
            if ($d['key'] == 'server') {
                $server = $d['value'];
            }
            if ($d['key'] == 'user') {
                $user = $d['value'];
            }
            if ($d['key'] == 'password') {
                $password = $d['value'];
            }
        }

        return generalResponse(
            'Success',
            false,
            [
                'folder' => $folder,
                'server' => $server,
                'user' => $user,
                'password' => $password,
            ],
        );
    }

    public function initAddonsFolder()
    {
        $this->createUrl('fileStations');
        $payload = [
            'api' => 'SYNO.FileStation.List',
            'version' => '2',
            'method' => 'list',
            '_sid' => $this->sid,
            'folder_path' => $this->config['data']['folder'],
        ];
        $response = Http::get($this->url, $payload);

        $body = json_decode($response->body(), true);

        if ($body['success'] == FALSE) {
            return [
                'error' => true,
                'message' => 'Cannot get default folder configuration',
            ];
        }

        return [
            'error' => false,
            'data' => json_decode($response->body(), true),
            'message' => 'Success',
        ];
    }

    public function createNASFolder(string $path, string $name)
    {
        $this->createUrl('fileStations');

        $response = Http::get($this->url, [
            'api' => 'SYNO.FileStation.CreateFolder',
            'version' => '2',
            'method' => 'create',
            'folder_path' => $path,
            'name' => $name,
            '_sid' => $this->sid,
            'force_parent' => true,
        ]);

        $response = json_decode($response->body(), true);

        return $response;
    }

    public function uploadFile(string $path, string $name, string $mime, string $targetPath)
    {
        $this->createUrl('fileStations');

        $this->url .= "?api=SYNO.FileStation.Upload&version=2&method=upload&path={$targetPath}&create_parents=true&_sid={$this->sid}&overwrite=true&mtime";
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => array('filename'=> new CURLFile($path, $mime, $name)),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * Delete NAS Folder
     *
     * @param string $folderPath
     * @return array
     */
    public function deleteFolder(string $folderPath)
    {
        $this->createUrl('fileStations');

        $response = Http::get($this->url, [
            'api' => 'SYNO.FileStation.Delete',
            'version' => '2',
            'method' => 'delete',
            'path' => $folderPath,
            'recursive' => true,
            '_sid' => $this->sid,
        ]);

        $body = json_decode($response->body(), true);

        return $body;
    }

    public function download(string $path)
    {
        $this->createUrl('fileStations');

        // $response = Http::get($this->url, [
        //     'api' => 'SYNO.FileStation.Download',
        //     'version' => '2',
        //     '_sid' => $this->sid,
        //     'method' => 'download',
        //     'path' => $path,
        //     'mode' => 'download',
        // ]);

        $query = http_build_query([
            'api' => 'SYNO.FileStation.Download',
            'version' => '2',
            '_sid' => $this->sid,
            'method' => 'download',
            'path' => $path,
            'mode' => 'download',
        ]);

        // $body = json_decode($response->body(), true);

        return [
            'url' => $this->url . '?' . $query,
        ];
    }
}