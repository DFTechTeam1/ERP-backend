<?php

namespace Tests\Feature\Employee;

use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\UserService;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Company\Database\Factories\ProvinceFactory;
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

class GetAllStatusTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    private $service;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        
        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        ProvinceFactory::$sequence = 1;

        $this->token = $this->getToken($userData['user']);

        $userServiceMock = $this->instance(
            abstract: UserService::class,
            instance: Mockery::mock(UserService::class)
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
            new EmployeeEmergencyContactRepository,
            $userServiceMock,
            new GeneralService
        );
    }

    /**
     * A basic feature test example.
     */
    public function testGetAllStatusServiceIsWorking(): void
    {
        $response = $this->service->getAllStatus();

        $this->assertFalse($response['error']);
    }

    public function testGetStatusRoute(): void
    {
        $response = $this->getJson(route('api.employees.getAllStatus'), [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);
    }
}
