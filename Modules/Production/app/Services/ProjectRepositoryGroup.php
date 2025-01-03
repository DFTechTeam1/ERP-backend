<?php

namespace Modules\Production\Services;

use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskHoldRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;

class ProjectRepositoryGroup {
    public $projectBoardRepo;

    public $projectTaskHoldRepo;

    public $projectTaskRepo;

    public $projectPicRepo;

    public $transferTeamRepo;

    public $taskPicHistoryRepo;

    public $taskPicRepo;

    public $projectRepo;

    public $taskPicLogRepo;

    public function __construct(
        ProjectRepository $projectRepo,
        ProjectBoardRepository $projectBoardRepo,
        ProjectTaskHoldRepository $projectTaskHoldRepo,
        ProjectTaskRepository $projectTaskRepo,
        ProjectPersonInChargeRepository $projectPicRepo,
        TransferTeamMemberRepository $transferTeamRepo,
        ProjectTaskPicHistoryRepository $taskPicHistoryRepo,
        ProjectTaskPicRepository $taskPicRepo,
        ProjectTaskPicLogRepository $taskPicLogRepo
    )
    {
        $this->projectRepo = $projectRepo;
        $this->projectBoardRepo = $projectBoardRepo;
        $this->projectTaskHoldRepo = $projectTaskHoldRepo;
        $this->projectTaskRepo = $projectTaskRepo;
        $this->projectPicRepo = $projectPicRepo;
        $this->transferTeamRepo = $transferTeamRepo;
        $this->taskPicHistoryRepo = $taskPicHistoryRepo;
        $this->taskPicRepo = $taskPicRepo;
        $this->taskPicLogRepo = $taskPicLogRepo;
    }
}