<?php

namespace Modules\Nas\Services;

use Modules\Company\Models\Setting;

class NasService
{
    public function setIp(string $ip)
    {
        $currentIp = Setting::select('id')
            ->where('key', 'nas_current_ip')
            ->first();
        if ($currentIp) {
            $currentIp->value = $ip;
            $currentIp->save();
        } else {
            Setting::create([
                'key' => 'nas_current_ip',
                'value' => $ip,
            ]);
        }
    }

    public function setRoot(string $rootName)
    {
        $currentRoot = Setting::select('id')
            ->where('key', 'nas_current_root')
            ->first();
        if ($currentRoot) {
            $currentRoot->value = $rootName;
            $currentRoot->save();
        } else {
            Setting::create([
                'key' => 'nas_current_root',
                'value' => $rootName,
            ]);
        }
    }

    public function getConfiguration()
    {
        $ip = Setting::select('value')
            ->getIp()
            ->first();
        $root = Setting::select('value')
            ->getRoot()
            ->first();

        return [
            'ip' => $ip ? $ip->value : null,
            'root' => $root ? $root->value : null,
        ];
    }

    public function deleteConfiguration()
    {
        Setting::where('key', 'nas_current_ip')->delete();
        Setting::where('key', 'nas_current_root')->delete();
    }
}
