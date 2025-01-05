<?php

namespace Tests\Feature\Employee;

use App\Repository\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Models\Employee;
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

class GetAllEmployeeTest extends TestCase
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
            new EmployeeRepository,
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
    public function testAllEmployeeWithMinLevelParam(): void
    {
        // create manager
        $totalManager = 2;
        Employee::factory()->count($totalManager)->create([
            'level_staff' => 'manager'
        ]);

        request()->merge(['min_level', 'manager']);

        $response = $this->service->getAll();

        $this->assertCount($totalManager, $response['data']);
    }
}
