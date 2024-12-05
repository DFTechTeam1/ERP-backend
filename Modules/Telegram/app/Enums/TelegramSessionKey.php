<?php

namespace Modules\Telegram\Enums;

enum TelegramSessionKey: string
{
    case WaitingRootFolderName = 'waiting_root_folder_name';

    case WaitingNasIp = 'waiting_nas_ip';

    public static function getAction(string $key)
    {
        switch ($key) {
            case self::WaitingRootFolderName->value:
                $action = 'registerRootFolderName';
                break;

            case self::WaitingNasIp->value:
                $action = 'registerNasIp';
                break;

            default:
                $action = null;
                break;
        }

        return $action;
    }
}
