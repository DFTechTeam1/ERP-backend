<?php

namespace Modules\Production\Services;

use App\Enums\Entertainment\LogType;
use App\Models\User;
use Modules\Production\Repository\EntertainmentLogRepository;

class EntertainmentLogService {
    public function __construct(
        private readonly EntertainmentLogRepository $logRepo
    ) {}

    public function compose(LogType $type, array $replace = []): string
    {
        $message = $type->message();
        $params = $type->messageParam();

        if (empty($params)) return $message;

        return str_replace(
            search: $params,
            replace: $replace,
            subject: $message
        );
    }

    public function record(int $projectId, User $actor)
    {
        if ($actor->employee_id && ! $actor->employee) {
            $actor->load('employee:id,nickname');
        }
        // $this->logRepo->store(data: [
        //     'description' => 
        // ]);
    }
}