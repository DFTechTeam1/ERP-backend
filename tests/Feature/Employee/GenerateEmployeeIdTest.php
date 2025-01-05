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

class GenerateEmployeeIdTest extends TestCase
{
    use RefreshDatabase;

    private $service;

    private $employeeRepoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeMock = Mockery::mock(EmployeeRepository::class);

        $this->employeeRepoMock = $this->instance(EmployeeRepository::class, $employeeMock);

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
    public function testPrefixEmployeeId(): void
    {
        $this->employeeRepoMock
            ->shouldReceive('list')
            ->atMost(1)
            ->with(
                'id',
                '',
                [],
                'id DESC'
            )
            ->andReturn([]);
            
        $response = $this->service->generateEmployeeID();
        
        $this->assertFalse($response['error']);
        $this->assertStringContainsString('DF', $response['data']['employee_id']);
    }

    public function testFirstEmployeeId(): void
    {
        $this->employeeRepoMock
            ->shouldReceive('list')
            ->atMost(1)
            ->with(
                'id',
                '',
                [],
                'id DESC'
            )
            ->andReturn([]);

        $response = $this->service->generateEmployeeID();

        $this->assertTrue(\Illuminate\Support\Str::endsWith($response['data']['employee_id'], '1'));
    }

    public function testSecondEmployeeId()
    {
        $this->employeeRepoMock
            ->shouldReceive('list')
            ->atMost(1)
            ->with(
                'id',
                '',
                [],
                'id DESC'
            )
            ->andReturn([['id' => 1]]);

        $response = $this->service->generateEmployeeID();
        
        $this->assertTrue(\Illuminate\Support\Str::endsWith($response['data']['employee_id'], '2'));
    }
}
