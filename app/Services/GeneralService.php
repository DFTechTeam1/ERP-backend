<?php

/**
 * This is a brigde between apps and all global helper functions
 * All services, repo, controller should call this service when they need to communicate with global function
 */

namespace App\Services;

use Carbon\Carbon;

class GeneralService {
    public function getIdFromUid(string $uid, $model)
    {
        return getIdFromUid($uid, $model);
    }

    public function getSettingByKey(string $param)
    {
        return getSettingByKey($param);
    }

    public function formatNotifications(array $param)
    {
        return formatNotifications($param);
    }

    public function getClientIp()
    {
        return getClientIp();
    }

    public function parseUserAgent($param)
    {
        return parseUserAgent($param);
    }

    public function getUserAgentInfo()
    {
        return getUserAgentInfo();
    }

    public function generateRandomPassword(int $length)
    {
        return generateRandomPassword($length);
    }

    public function getCache(string $cacheId)
    {
        return getCache($cacheId);
    }

    public function uploadImageandCompress(
        string $path,
        int $compressValue,
        $image,
        string $extTarget = 'webp',
    ) {
        return uploadImageandCompress($path, $compressValue, $image, $extTarget);
    }

    public function reportPerformanceDefaultDate(): array
    {
        $now = Carbon::now();

        $startDate = $now->copy()->subMonthNoOverflow()->day(24);
        $endDate = $now->copy()->day(23);

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }
}