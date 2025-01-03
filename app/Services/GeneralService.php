<?php

/**
 * This is a brigde between apps and all global helper functions
 * All services, repo, controller should call this service when they need to communicate with global function
 */

namespace App\Services;

class GeneralService {
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
}