<?php

namespace Tests\Feature;

use App\Enums\Employee\Status;
use App\Traits\HasEmployeeConstructor;
use App\Traits\TestUserAuthentication;
use Database\Factories\Hrd\EmployeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Modules\Company\Database\Factories\ProvinceFactory;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Models\Project;
use Tests\TestCase;

class DeleteEmployeeTest extends TestCase
{
    use RefreshDatabase, HasEmployeeConstructor, TestUserAuthentication;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();

        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);
        
        $this->token = $this->getToken($userData['user']);

        ProvinceFactory::$sequence = 10;
    }

    /**
     * A basic feature test example.
     */
    public function testBulkDeleteEmployeeWithHasNoRelationAndReturnSuccess(): void
    {
        $this->setConstructor();

        $employees = Employee::factory()
            ->hasUser(1)
            ->count(1)->create([
                'employee_id' => 'DF100'
            ]);

        $payload = [
            $employees[0]->uid
        ];

        $response = $this->employeeService->bulkDelete($payload);

        $this->assertFalse($response['error']); 
        $this->assertEquals(__('global.successDeleteEmployee'), $response['message']);
        $this->assertDatabaseHas('employees', ['status' => Status::Deleted->value]);

        parent::tearDown();
    }

    public function testDeleteEmployeeWithRelationReturnFailed(): void
    {  
        $employee = new Employee(['id' => 1, 'name' => 'testing']);
        $employee->projects = collect([
            ['id' => 1, 'name' => 'projects']
        ]);
        $employee->tasks = collect([]);

        $employeeRepoMock = $this->instance(
            abstract: EmployeeRepository::class,
            instance: Mockery::mock(EmployeeRepository::class, function (MockInterface $mock) use ($employee) {
                $mock->shouldReceive('show')
                ->once()
                ->with(
                    'id',
                    'id,name,email',
                    [
                        'tasks:id,project_task_id,employee_id',
                        'user:id,employee_id,uid',
                        'projects:id,project_id,pic_id'
                    ]
                )
                ->andReturn($employee);
            })
        );

        $this->setConstructor(
            employeeRepo: $employeeRepoMock
        );

        $response = $this->employeeService->bulkDelete(['id']);

        $this->assertTrue($response['error']);
        $this->assertEquals(__('notification.cannotDeleteEmployeeBcsRelation'), $response['message']);

        parent::tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
