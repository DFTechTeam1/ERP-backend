<?php

namespace Modules\Production\Services;

use App\Enums\Entertainment\LogType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

    public function checkClass(Model $model, mixed $targetModel): bool
    {
        return get_class($model) === $targetModel;
    }

    public function record(int $projectId, LogType $type, array $replace = [])
    {
        app(\Modules\Production\Repository\EntertainmentLogRepository::class)->store([
            'description' => $this->compose($type, $replace),
            'project_id' => $projectId
        ]);
    }

    public function recordByModel(Model $model) {
        if (self::checkClass($model, \Modules\Production\Models\EntertainmentTask::class)) {
            $employee = app(\Modules\Hrd\Repository\EmployeeRepository::class)->show(uid: '', select: 'id,nickname', where: "user_id = " . Auth::id());

            $this->record(
                projectId: $model->project_id,
                type: LogType::createSong,
                replace: [$employee->nickname]
            );
        }
    }
}