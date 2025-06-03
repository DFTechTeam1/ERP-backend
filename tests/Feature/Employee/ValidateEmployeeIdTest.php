<?php

namespace Tests\Feature\Employee;

use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeEmergencyContactRepository;
use Modules\Hrd\Repository\EmployeeFamilyRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeeService;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectVjRepository;
use Tests\TestCase;

class ValidateEmployeeIdTest extends TestCase
{
    use RefreshDatabase;

    private $service;

    private $employeeRepoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRepoMock = Mockery::mock(EmployeeRepository::class);

        $this->employeeRepoMock = $this->instance(
            abstract: EmployeeRepository::class,
            instance: $employeeRepoMock
        );

        $userServiceMock = $this->instance(
            abstract: UserService::class,
            instance: Mockery::mock(UserService::class)
        );

        $this->service = new EmployeeService(
            $this->employeeRepoMock,
            new PositionRepository,
            new UserRepository,
            new ProjectTaskRepository,
            new ProjectRepository,
            new ProjectVjRepository,
            new ProjectPersonInChargeRepository,
            new ProjectTaskPicHistoryRepository,
            new EmployeeFamilyRepository,
            new EmployeeEmergencyContactRepository,
            $userServiceMock,
            new GeneralService
        );
    }

    /**
     * A basic feature test example.
     */
    public function test_employee_id_wrong(): void
    {
        $this->employeeRepoMock
            ->shouldReceive('show')
            ->once()
            ->with(
                'id',
                'id',
                [],
                "employee_id = 'DF001'"
            )
            ->andReturnTrue();

        $response = $this->service->validateEmployeeID(['employee_id' => 'DF001', 'uid' => 0]);

        $this->assertFalse($response['data']['valid']);
    }

    public function test_employee_id_is_valid(): void
    {
        $this->employeeRepoMock
            ->shouldReceive('show')
            ->once()
            ->with(
                'id',
                'id',
                [],
                "employee_id = 'DF001'"
            )
            ->andReturnNull();

        $response = $this->service->validateEmployeeID(['employee_id' => 'DF001', 'uid' => 0]);

        $this->assertTrue($response['data']['valid']);
    }
}
