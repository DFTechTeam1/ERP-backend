<?php

namespace App\Traits;

use App\Actions\Project\DetailCache;
use App\Actions\Project\DetailProject;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\Geocoding;
use App\Services\TestProjectConstructor;
use App\Services\UserRoleManagement;
use Modules\Company\Repository\PositionRepository;
use Modules\Company\Repository\ProjectClassRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeTaskPointRepository;
use Modules\Hrd\Repository\EmployeeTaskStateRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultImageRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultRepository;
use Modules\Production\Repository\EntertainmentTaskSongReviseRepository;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectReferenceRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Repository\ProjectTaskAttachmentRepository;
use Modules\Production\Repository\ProjectTaskHoldRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskProofOfWorkRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectTaskReviseHistoryRepository;
use Modules\Production\Repository\ProjectTaskWorktimeRepository;
use Modules\Production\Repository\ProjectVjRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;
use Modules\Production\Services\EntertainmentTaskSongLogService;
use Modules\Production\Services\ProjectRepositoryGroup;
use Modules\Production\Services\ProjectService;

trait HasProjectConstructor
{
    public $projectService;

    public function setProjectConstructor(
        $userRoleManagement = null,
        $projectBoardRepo = null,
        $geocoding = null,
        $projectTaskHoldRepo = null,
        $projectVjRepo = null,
        $inventoryItemRepo = null,
        $projectClassRepo = null,
        $projectRepo = null,
        $projectReferenceRepo = null,
        $employeeRepo = null,
        $projectTaskRepo = null,
        $projectTaskPicRepo = null,
        $projectEquipmentRepo = null,
        $projectTaskAttachmentRepo = null,
        $projectPersonInChargeRepo = null,
        $projectTaskLogRepo = null,
        $projectTaskProofOfWorkRepo = null,
        $projectTaskWorktimeRepo = null,
        $positionRepo = null,
        $projectTaskPicLogRepo = null,
        $projectTaskReviseHistoryRepo = null,
        $transferTeamMemberRepo = null,
        $employeeTaskPointRepo = null,
        $projectTaskPicHistoryRepo = null,
        $customInventoryRepo = null,
        $projectSongListRepo = null,
        $generalService = null,
        $entertainSongRepo = null,
        $entertainmentTaskSongLogService = null,
        $userRepo = null,
        $detailProjectAction = null,
        $detailCacheAction = null,
        $entertainmentTaskSongResultRepo = null,
        $entertainmentTaskSongResultImageRepo = null,
        $entertainmentTaskSongRevise = null,
        $employeeTaskStateRepo = null
    )
    {
        $this->projectService = new ProjectService(
            $userRoleManagement ? $userRoleManagement :new UserRoleManagement,
            $projectBoardRepo ? $projectBoardRepo : new ProjectBoardRepository,
            $geocoding ? $geocoding : new Geocoding,
            $projectTaskHoldRepo ? $projectTaskHoldRepo : new ProjectTaskHoldRepository,
            $projectVjRepo ? $projectVjRepo : new ProjectVjRepository,
            $inventoryItemRepo ? $inventoryItemRepo : new InventoryItemRepository,
            $projectClassRepo ? $projectClassRepo : new ProjectClassRepository,
            $projectRepo ? $projectRepo : new ProjectRepository,
            $projectReferenceRepo ? $projectReferenceRepo : new ProjectReferenceRepository,
            $employeeRepo ? $employeeRepo : new EmployeeRepository,
            $projectTaskRepo ? $projectTaskRepo : new ProjectTaskRepository,
            $projectTaskPicRepo ? $projectTaskPicRepo : new ProjectTaskPicRepository,
            $projectEquipmentRepo ? $projectEquipmentRepo : new ProjectEquipmentRepository,
            $projectTaskAttachmentRepo ? $projectTaskAttachmentRepo : new ProjectTaskAttachmentRepository,
            $projectPersonInChargeRepo ? $projectPersonInChargeRepo : new ProjectPersonInChargeRepository,
            $projectTaskLogRepo ? $projectTaskLogRepo : new ProjectTaskLogRepository,
            $projectTaskProofOfWorkRepo ? $projectTaskProofOfWorkRepo : new ProjectTaskProofOfWorkRepository,
            $projectTaskWorktimeRepo ? $projectTaskWorktimeRepo : new ProjectTaskWorktimeRepository,
            $positionRepo ? $positionRepo : new PositionRepository,
            $projectTaskPicLogRepo ? $projectTaskPicLogRepo : new ProjectTaskPicLogRepository,
            $projectTaskReviseHistoryRepo ? $projectTaskReviseHistoryRepo : new ProjectTaskReviseHistoryRepository,
            $transferTeamMemberRepo ? $transferTeamMemberRepo : new TransferTeamMemberRepository,
            $employeeTaskPointRepo ? $employeeTaskPointRepo : new EmployeeTaskPointRepository,
            $projectTaskPicHistoryRepo ? $projectTaskPicHistoryRepo : new ProjectTaskPicHistoryRepository,
            $customInventoryRepo ? $customInventoryRepo : new CustomInventoryRepository,
            $projectSongListRepo ? $projectSongListRepo : new ProjectSongListRepository,
            $generalService ? $generalService : new GeneralService,
            $entertainSongRepo ? $entertainSongRepo : new EntertainmentTaskSongRepository,
            $entertainmentTaskSongLogService ? $entertainmentTaskSongLogService : new EntertainmentTaskSongLogService,
            $userRepo ? $userRepo : new UserRepository,
            $detailProjectAction ? $detailProjectAction : new DetailProject,
            $detailCacheAction ? $detailCacheAction : new DetailCache,
            $entertainmentTaskSongResultRepo ? $entertainmentTaskSongResultRepo : new EntertainmentTaskSongResultRepository,
            $entertainmentTaskSongResultImageRepo ? $entertainmentTaskSongResultImageRepo : new EntertainmentTaskSongResultImageRepository,
            $entertainmentTaskSongRevise ? $entertainmentTaskSongRevise : new EntertainmentTaskSongReviseRepository,
            $employeeTaskStateRepo ? $employeeTaskStateRepo : new EmployeeTaskStateRepository
        );
    }
}
