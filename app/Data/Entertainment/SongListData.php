<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class SongListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly ?string $group,
        public readonly string $status,
        public readonly string $status_text,
        public readonly string $status_color,
        public readonly ?string $status_request,
        public readonly bool $my_own,
        public readonly string $formatted_created_at,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
        public readonly bool $can_distribute,
        public readonly bool $can_start_work,
        public readonly bool $can_approve_work,
        public readonly bool $can_take_action,
        public readonly bool $is_request_edit,
        public readonly bool $is_request_delete,
        public readonly ?SongTaskData $task,
    ) {}
}
