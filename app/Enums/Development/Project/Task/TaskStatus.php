<?php

namespace App\Enums\Development\Project\Task;

enum TaskStatus: int {
    case WaitingApproval = 1;
    case InProgress = 2;
    case Completed = 3;
}