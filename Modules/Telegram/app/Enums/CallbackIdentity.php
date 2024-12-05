<?php

namespace Modules\Telegram\Enums;

enum CallbackIdentity: string
{
    case MyProject = 'my_project';
    case MyTask = 'my_task';
    case Back = 'back';
    case ApproveTask = 'ptappv';
    case CheckProofOfWork = 'cpofwork';
    case MarkTaskAsComplete = 'mtccomplete';
    case SetActiveIP = 'sacipnas';
    case SetActiveRoot = 'saronas';
    case GetNasConfiguration = 'gnasconf';
    case DeleteNasConfiguration = 'dnasconf';
}
