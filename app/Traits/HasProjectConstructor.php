<?php

namespace App\Traits;

use App\Services\TestProjectConstructor;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskHoldRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;
use Modules\Production\Services\ProjectRepositoryGroup;

trait HasProjectConstructor
{
    public $projectGroupRepo;

    private function getListOfRepository()
    {
        return [
            'projectRepo' => ProjectRepository::class,
            'projectBoardRepo' => ProjectBoardRepository::class,
            'projectTaskHoldRepo' => ProjectTaskHoldRepository::class,
            'projectTaskRepo' => ProjectTaskRepository::class,
            'projectPicRepo' => ProjectPersonInChargeRepository::class,
            'transferTeamRepo' => TransferTeamMemberRepository::class,
            'taskPicHistoryRepo' => ProjectTaskPicHistoryRepository::class,
            'taskPicRepo' => ProjectTaskPicRepository::class,
            'taskPicLogRepo' => ProjectTaskPicLogRepository::class
        ];
    }

    public function getProjectGroupConstructor($testCase)
    {
        $projectRepo = new ProjectRepository();
        $projectBoardRepo = new ProjectBoardRepository();
        $projectTaskHoldRepo = new ProjectTaskHoldRepository();
        $projectTaskRepo = new ProjectTaskRepository();
        $projectPicRepo = new ProjectPersonInChargeRepository();
        $transferTeamRepo = new TransferTeamMemberRepository();
        $projectTaskPicHistoryRepo = new ProjectTaskPicHistoryRepository();
        $projectTaskPicRepo = new ProjectTaskPicRepository();
        $projectTaskPicLogRepo = new ProjectTaskPicLogRepository();

        $this->projectGroupRepo = new ProjectRepositoryGroup(
            $projectRepo,
            $projectBoardRepo,
            $projectTaskHoldRepo,
            $projectTaskRepo,
            $projectPicRepo,
            $transferTeamRepo,
            $projectTaskPicHistoryRepo,
            $projectTaskPicRepo,
            $projectTaskPicLogRepo
        );
    }
}
