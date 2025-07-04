<?php

use App\Actions\Project\DetailCache;
use App\Actions\Project\DetailProject;
use App\Enums\Production\ProjectDealStatus;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\Geocoding;
use App\Services\UserRoleManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\State;
use Modules\Company\Repository\PositionRepository;
use Modules\Company\Repository\ProjectClassRepository;
use Modules\Company\Repository\SettingRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Finance\Services\TransactionService;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeTaskPointRepository;
use Modules\Hrd\Repository\EmployeeTaskStateRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Production\Models\Customer;
use Modules\Production\Models\QuotationItem;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultImageRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultRepository;
use Modules\Production\Repository\EntertainmentTaskSongReviseRepository;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectDealMarketingRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectQuotationRepository;
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
use Modules\Production\Services\ProjectService;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createProjectService(
    $userRoleManagement = null,
    $projectBoardRepo = null,
    $geoCoding = null,
    $projectTaskHoldRepo = null,
    $projectVjRepo = null,
    $inventoryItemRepo = null,
    $projectClassRepo = null,
    $projectRepo = null,
    $projectRefRepo = null,
    $employeeRepo = null,
    $projectTaskRepo = null,
    $projectTaskPicRepo = null,
    $projectEquipmentRepo = null,
    $projectTaskAttachmentRepo = null,
    $projectPicRepo = null,
    $projectTaskLogRepo = null,
    $projectProofOfWorkRepo = null,
    $projectTaskWorktimeRepo = null,
    $positionRepo = null,
    $taskPicLogRepo = null,
    $taskReviseHistoryRepo = null,
    $transferTeamRepo = null,
    $employeeTaskPoint = null,
    $taskPicHistory = null,
    $customItemRepo = null,
    $projectSongListRepo = null,
    $generalService = null,
    $entertainmentTaskSongRepo = null,
    $entertainmentTaskSongLogService = null,
    $userRepo = null,
    $detailProjectAction = null,
    $detailCacheAction = null,
    $entertainmentTaskSongResultRepo = null,
    $entertainmentTaskSongResultImageRepo = null,
    $entertainmentTaskSongRevise = null,
    $employeeTaskStateRepo = null,
    $settingRepo = null,
    $projectQuotationRepo = null,
    $projectDealRepo = null,
    $projectDealMarketingRepo = null
)
{
    return new ProjectService(
        $userRoleManagement ? $userRoleManagement : new UserRoleManagement(),
        $projectBoardRepo ? $projectBoardRepo : new ProjectBoardRepository,
        $geoCoding ? $geoCoding : new Geocoding,
        $projectTaskHoldRepo ? $projectTaskHoldRepo : new ProjectTaskHoldRepository,
        $projectVjRepo ? $projectVjRepo : new ProjectVjRepository,
        $inventoryItemRepo ? $inventoryItemRepo : new InventoryItemRepository,
        $projectClassRepo ? $projectClassRepo : new ProjectClassRepository,
        $projectRepo ? $projectRepo : new ProjectRepository,
        $projectRefRepo ? $projectRefRepo : new ProjectReferenceRepository,
        $employeeRepo ? $employeeRepo : new EmployeeRepository,
        $projectTaskRepo ? $projectTaskRepo : new ProjectTaskRepository,
        $projectTaskPicRepo ? $projectTaskPicRepo : new ProjectTaskPicRepository,
        $projectEquipmentRepo ? $projectEquipmentRepo : new ProjectEquipmentRepository,
        $projectTaskAttachmentRepo ? $projectTaskAttachmentRepo : new ProjectTaskAttachmentRepository,
        $projectPicRepo ? $projectPicRepo : new ProjectPersonInChargeRepository,
        $projectTaskLogRepo ? $projectTaskLogRepo : new ProjectTaskLogRepository,
        $projectProofOfWorkRepo ? $projectProofOfWorkRepo : new ProjectTaskProofOfWorkRepository,
        $projectTaskWorktimeRepo ? $projectTaskWorktimeRepo : new ProjectTaskWorktimeRepository,
        $positionRepo ? $positionRepo : new PositionRepository,
        $taskPicLogRepo ? $taskPicLogRepo : new ProjectTaskPicLogRepository,
        $taskReviseHistoryRepo ? $taskReviseHistoryRepo : new ProjectTaskReviseHistoryRepository,
        $transferTeamRepo ? $transferTeamRepo : new TransferTeamMemberRepository,
        $employeeTaskPoint ? $employeeTaskPoint : new EmployeeTaskPointRepository,
        $taskPicHistory ? $taskPicHistory : new ProjectTaskPicHistoryRepository,
        $customItemRepo ? $customItemRepo : new CustomInventoryRepository,
        $projectSongListRepo ? $projectSongListRepo : new ProjectSongListRepository,
        $generalService ? $generalService : new GeneralService,
        $entertainmentTaskSongRepo ? $entertainmentTaskSongRepo : new EntertainmentTaskSongRepository,
        $entertainmentTaskSongLogService ? $entertainmentTaskSongLogService : new EntertainmentTaskSongLogService,
        $userRepo ? $userRepo : new UserRepository,
        $detailProjectAction ? $detailProjectAction : new DetailProject,
        $detailCacheAction ? $detailCacheAction : new DetailCache,
        $entertainmentTaskSongResultRepo ? $entertainmentTaskSongResultRepo : new EntertainmentTaskSongResultRepository,
        $entertainmentTaskSongResultImageRepo ? $entertainmentTaskSongResultImageRepo : new EntertainmentTaskSongResultImageRepository,
        $entertainmentTaskSongRevise ? $entertainmentTaskSongRevise : new EntertainmentTaskSongReviseRepository,
        $employeeTaskStateRepo ? $employeeTaskStateRepo : new EmployeeTaskStateRepository,
        $settingRepo ? $settingRepo : new SettingRepository,
        $projectQuotationRepo ? $projectQuotationRepo : new ProjectQuotationRepository,
        $projectDealRepo ? $projectDealRepo : new ProjectDealRepository,
        $projectDealMarketingRepo ? $projectDealMarketingRepo : new ProjectDealMarketingRepository
    );
}

function initAuthenticateUser()
{
    $user = \App\Models\User::factory()
        ->create();

    $checkRoot = \Illuminate\Support\Facades\DB::table('roles')
        ->where('name', \App\Enums\System\BaseRole::Root->value)
        ->first();

    if (!$checkRoot) {
        $checkRoot = \Spatie\Permission\Models\Role::create(['name' => \App\Enums\System\BaseRole::Root->value, 'guard_name' => 'sanctum']);
    }

    $user->assignRole($checkRoot);

    return $user;
}

function prepareProjectDeal(array $payload)
{
    // create customer first
    $customer = Customer::factory()
        ->count(1)
        ->create();
    $payload['customer_id'] = $customer[0]->id;

    // create quotation item
    $items = QuotationItem::factory()
        ->count(2)
        ->create();
    $itemIds = collect($items)->pluck('id')->toArray();
    $payload['quotation']['items'] = $itemIds;

    // create project class
    $projectClass = ProjectClass::create([
        'name' => 'B',
        'maximal_point' => 20,
    ]);
    $payload['project_class_id'] = $projectClass->id;

    // create marketing data
    $employee = Employee::factory()
        ->count(1)
        ->create();
    $payload['marketing_id'] = [$employee[0]->uid];

    return $payload;
}

function getProjectDealPayload(
    object $customer,
    ?object $projectClass = null,
    ?object $employee = null,
    ?object $quotationItem = null
) {
    $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();

    return [
        'name' => 'Project Testing',
        'project_date' => '2025-06-30',
        'customer_id' => $customer->id,
        'event_type' => 'wedding',
        'venue' => 'Grand Hall',
        'collaboration' => null,
        'note' => null,
        'led_area' => 110,
        'led_detail' => [
            [
                'name' => 'main',
                'led' => [
                    [
                        'height' => '5.5',
                        'width' => '20'
                    ]
                ],
                'total' => '110 m<sup>2</sup>',
                'totalRaw' => '110',
                'textDetail' => '20 x 5.5 m'
            ]
        ],
        'country_id' => $country->id,
        'state_id' => $country->states[0]->id,
        'city_id' => $country->states[0]->cities[0]->id,
        'project_class_id' => $projectClass ? $projectClass->id : 1,
        'longitude' => fake()->longitude(),
        'latitude' => fake()->latitude(),
        'equipment_type' => 'lasika',
        'is_high_season' => 1,
        'client_portal' => 'wedding-anniversary',
        'marketing_id' => [
            $employee ? $employee->uid : 'f063164d-62ff-44cf-823d-7c456dad1f4b'
        ],
        'status' => ProjectDealStatus::Draft->value, // 1 is active, 0 is draft
        'quotation' => [
            'quotation_id' => '#DF04022',
            'is_final' => 0,
            'event_location_guide' => 'surabaya',
            'main_ballroom' => 72000000,
            'prefunction' => 10000000,
            'high_season_fee' => 2500000,
            'equipment_fee' => 0,
            'sub_total' => 84500000,
            'maximum_discount' => 5000000,
            'total' => 84500000,
            'maximum_markup_price' => 90000000,
            'fix_price' => 85000000,
            'is_high_season' => 1,
            'equipment_type' => 'lasika',
            'items' => $quotationItem ? [$quotationItem->id] : [1, 2],
            'description' => '',
            'design_job' => 1
        ],
        'request_type' => 'save_and_download' // will be draft,save,save_and_download
    ];
}

function createProjectDealService(
    $projectDealRepo = null,
    $projectDealMarketingRepo = null,
    $generalService = null,
    $projectQuotationRepo = null,
    $projectRepo = null,
    $geocoding = null
) {
    return new \Modules\Production\Services\ProjectDealService(
        $projectDealRepo ? $projectDealRepo : new ProjectDealRepository(),
        $projectDealMarketingRepo ? $projectDealMarketingRepo : new ProjectDealMarketingRepository(),
        $generalService ? $generalService : new GeneralService(),
        $projectQuotationRepo ? $projectQuotationRepo : new ProjectQuotationRepository(),
        $projectRepo ? $projectRepo : new ProjectRepository,
        $geocoding ? $geocoding : new Geocoding
    );
}

function createQuotationItemService(
    $quotationItemRepo = null
) {
    return new \Modules\Production\Services\QuotationItemService(
        $quotationItemRepo ? $quotationItemRepo : new \Modules\Production\Repository\QuotationItemRepository()
    );
}

function setTransactionService(
    $repo = null,
    $projectQuotationRepo = null,
    $generalService = null,
    $projectDealRepo = null
)
{
    return new TransactionService(
        $repo ? $repo : new TransactionRepository,
        $projectQuotationRepo ? $projectQuotationRepo : new ProjectQuotationRepository,
        $generalService ? $generalService : new GeneralService,
        $projectDealRepo ? $projectDealRepo : new ProjectDealRepository
    );
}

function setInvoiceService(
    $repo = null,
    $projectDealRepo = null,
    $generalService = null,
    $transactionRepo = null
) {
    return new \Modules\Finance\Services\InvoiceService(
        $repo ? $repo : new \Modules\Finance\Repository\InvoiceRepository,
        $projectDealRepo ? $projectDealRepo : new \Modules\Production\Repository\ProjectDealRepository,
        $generalService ? $generalService : new \App\Services\GeneralService,
        $transactionRepo ? $transactionRepo : new \Modules\Finance\Repository\TransactionRepository
    );
}