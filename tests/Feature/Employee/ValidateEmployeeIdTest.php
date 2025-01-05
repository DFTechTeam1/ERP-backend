<?php

namespace Tests\Feature\Employee;

use App\Repository\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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
            new EmployeeEmergencyContactRepository
        );
    }

    /**
     * A basic feature test example.
     */
    public function testEmployeeIdWrong(): void
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

        $response = $this->service->validateEmployeeID(['employee_id' => 'DF001']);

        $this->assertFalse($response['data']['valid']);
    }

    public function testEmployeeIdIsValid(): void
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

        $response = $this->service->validateEmployeeID(['employee_id' => 'DF001']);

        $this->assertTrue($response['data']['valid']);
    }
}
