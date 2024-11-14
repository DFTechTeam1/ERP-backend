<?php

namespace Modules\Telegram\Enums;

enum CallbackIdentity: string
{
    case MyProject = 'my_project';
    case MyTask = 'my_task';
    case Back = 'back';

    case ApproveTask = 'ptappv';
}
