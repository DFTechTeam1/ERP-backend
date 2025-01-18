<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Repository\UserRepository;
use App\Traits\HasEmployeeConstructor;
use App\Traits\TestUserAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Modules\Hrd\Jobs\SendEmailActivationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Tests\TestCase;

class AddAsUserTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasEmployeeConstructor;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();

        Sanctum::actingAs($userData['user']);
        $this->actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    /**
     * A basic feature test example.
     */
    public function testAddUserWithMissingPayload(): void
    {
        $payload = [
            'user_id' => '',
            'password' => ''
        ];

        $response = $this->postJson(route('api.employees.addAsUser'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('user_id', $response['errors']);
        $this->assertStringContainsString('required', $response['errors']['user_id'][0]);

        parent::tearDown();
    }

    public function testAddExisitingEmail(): void
    {
        $userRepoMock = $this->instance(
            abstract: UserRepository::class,
            instance: Mockery::mock(UserRepository::class, function (MockInterface $mock) {
                $mock->shouldReceive('detail')
                    ->atMost(1)
                    ->with(
                        '',
                        'id',
                        "email = 'email@gmail.com'"
                    )
                    ->andReturn(
                        new User([
                            'id' => 1,
                            'email' => 'email@gmail.com'
                        ])
                    );
            })
        );

        $employeeRepoMock = $this->instance(
            abstract: EmployeeRepository::class,
            instance: Mockery::mock(EmployeeRepository::class, function (MockInterface $mock) {
                $mock->shouldReceive('show')
                    ->atMost(1)
                    ->with(
                        'userid',
                        'id,email,name'
                    )
                    ->andReturn(
                        new Employee([
                            'id' => 1,
                            'email' => 'email@gmail.com'
                        ])
                    );
            })
        );

        $payload = [
            'user_id' => 'userid',
            'password' => 'password'
        ];

        $this->setConstructor(
            userRepo: $userRepoMock,
            employeeRepo: $employeeRepoMock
        );

        $response = $this->employeeService->addAsUser($payload);
        $this->assertTrue($response['error']);
        $this->assertTrue($response['code'] == 500);

        parent::tearDown();
    }

    public function testAddAssUserSuccess(): void
    {
        Bus::fake();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $payload = [
            'user_id' => $employees[0]->uid,
            'password' => 'password'
        ];

        $response = $this->postJson(route('api.employees.addAsUser'), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);
        $response->assertStatus(201);

        Bus::assertDispatched(SendEmailActivationJob::class);

        $this->assertDatabaseHas('users', ['email' => $employees[0]->email]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
