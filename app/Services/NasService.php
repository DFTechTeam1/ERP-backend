<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Modules\Company\Models\Setting;

class NasService {
    private $url;
    
    public function createUrl(string $type)
    {
        $config = $this->addonConfiguration();
        $this->url = $config['data']['server'] . ':5000/webapi';
        if ($type == 'createFolder') {
            $this->url .= '/entry.cgi';
        }
    }

    /**
     * test connection to given configuration
     *
     * @param array $data
     * @return array
     */
    public function testConnection(array $data)
    {
        try {
            $http = 'http://' . $data['server'] . ':5000/webapi';
            $login = Http::get($http . '/auth.cgi', [
                'api' => 'SYNO.API.Auth',
                'version' => '3',
                'method' => 'login',
                'account' => $data['user'],
                'passwd' => $data['password'],
                'session' => 'FileStation',
                'format' => 'sid',
            ]);
    
            $login = json_decode($login->body(), true);

            if ($login['success'] == FALSE) {
                return errorResponse('Account is not valid');
            }

            // get the folder detail
            $folder = HTTP::get($http . '/entry.cgi', [
                'api' => 'SYNO.FileStation.List',
                'version' => '2',
                'method' => 'list',
                'folder_path' => $data['folder'],
                '_sid' => $login['data']['sid'],
            ]);

            $response = json_decode($folder->body(), true);

            if ($response['success'] == FALSE) {
                return errorResponse('Cannot get folder information');
            }
    
            return generalResponse(
                __('global.connectionIsSecure'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse('Cannot get to the given server');
        }
    }

    /**
     * Store addon configuration
     *
     * @param array $data
     * @return array
     */
    public function storeAddonConfiguration(array $data)
    {
        try {
            Setting::where('code', 'addon')->delete();

            Setting::create([
                'code' => 'addon',
                'key' => 'folder',
                'value' => $data['folder'],
            ]);
            Setting::create([
                'code' => 'addon',
                'key' => 'server',
                'value' => $data['server'],
            ]);
            Setting::create([
                'code' => 'addon',
                'key' => 'user',
                'value' => $data['user'],
            ]);
            Setting::create([
                'code' => 'addon',
                'key' => 'password',
                'value' => $data['password'],
            ]);

            \Illuminate\Support\Facades\Cache::forget('setting');

            return generalResponse(
                __('global.successSaveConfiguration'),
                false,
                $data,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
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
}