<?php

namespace Tests\Feature\Project;

use App\Enums\Production\ProjectStatus;
use App\Services\Geocoding;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Company\Models\City;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Models\Province;
use Modules\Company\Models\State;
use Modules\Company\Repository\CityRepository;
use Modules\Company\Repository\StateRepository;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

    private $token;

    private $cityMock;

    private $stateMock;

    private $geoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();

        $this->actingAs($userData['user']);
        Sanctum::actingAs($userData['user']);

        $this->token = $this->getToken($userData['user']);
    }

    protected function setPayload(string $projectName = ''): array
    {
        $projectClass = ProjectClass::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        return [
            'name' => $projectName,
            'marketing_id' => [$employees[0]->uid],
            'client_portal' => 'https://check.com',
            'project_date' => '2025-07-19',
            'event_type' => 'wedding',
            'venue' => 'Hotel Surabaya',
            'collaboration' => 'nuansa',
            'note' => NULL,
            'classification' => $projectClass[0]->id,
            'led_area' => '1',
            'led_detail' => '[{"name":"main","total":1,"totalRaw":1,"textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]',
            'status' => ProjectStatus::Draft->value,
            'country_id' => Province::factory()->create()->code,
            'state_id' => IndonesiaCity::factory()->create()->code,
            'city_id' => IndonesiaDistrict::factory()->create()->code,
        ];
    }

    protected function mockup(array $payload)
    {
        // mock city and latitude service
        $this->cityMock = $this->instance(
            abstract: CityRepository::class,
            instance: Mockery::mock(CityRepository::class)
        );
        $this->cityMock->shouldReceive('show')
            ->with($payload['city_id'], 'name')
            ->andReturn((object) ['name' => 'city name']);
        $this->stateMock = $this->instance(
            abstract: StateRepository::class,
            instance: Mockery::mock(StateRepository::class)
        );
        $this->stateMock->shouldReceive('show')
            ->with($payload['state_id'], 'name')
            ->andReturn((object) ['name' => 'state name']);

        $this->geoMock = $this->instance(
            abstract: Geocoding::class,
            instance: Mockery::mock(Geocoding::class)
        );
        $this->geoMock->shouldReceive('getCoordinate')
            ->withAnyArgs()
            ->andReturn([
                'latitude' => 1.233434,
                'longitude' => 1.233434,
            ]);
    }

    /**
     * A basic feature test example.
     */
    public function testCreateProjectWithWrongPayload(): void
    {
        $response = $this->postJson(route('api.production.project.store'), $this->setPayload(), [
            'Authorization' => $this->token,
        ]);

        $response->assertStatus(422);

        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('name', $response['errors']);
    }

    public function testCreateProjectReturnSuccess(): void
    {
        $payload = $this->setPayload(projectName: "Project testing");

        $this->mockup($payload);

        $response = $this->postJson(route('api.production.project.store'), $payload, [
            'Authorization' => $this->token,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('projects', ['name' => $payload['name'], 'project_date' => $payload['project_date']]);
    }
    
    public function testNasFolderCreatedAsExpected(): void
    {
        $payload = $this->setPayload(projectName: 'Birthday Project');

        $this->mockup($payload);

        $response = $this->postJson(route('api.production.project.store'), $payload, [
            'Authorization' => $this->token,
        ]);

        $response->assertStatus(201);

        // format folder name and etc
        $date = date('d', strtotime($payload['project_date']));
        $month = date('m', strtotime($payload['project_date']));
        $monthText = MonthInBahasa(date('m', strtotime($payload['project_date'])));
        $subFolder1 = strtoupper($month . '_' . $monthText);
        $prefixName = strtoupper($date . "_" . $monthText);
        
        $this->assertDatabaseHas('nas_folder_creation_backups', ['month_name' => $subFolder1, 'prefix_project_name' => $prefixName]);
    }

    public function testNasFolderUpdatedAsExpected(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create([
                'name' => 'project one'
            ]);

        $payload = [
            'name' => 'project two',
            'date' => $projects[0]->project_date,
            'event_type' => $projects[0]->event_type,
            'classification' => $projects[0]->project_class_id
        ];

        $response = $this->putJson(route('api.production.project.update-basic', ['projectId' => $projects[0]->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        $response->assertStatus(201);

        $name = preg_replace('/[.,\"~@\/]/', '', $projects[0]->name);
        $name = stringToPascalSnakeCase($name);

        $date = date('d', strtotime($projects[0]->project_date));
        $month = date('m', strtotime($projects[0]->project_date));
        $monthText = MonthInBahasa(date('m', strtotime($projects[0]->project_date)));
        $subFolder1 = strtoupper($month . '_' . $monthText);
        $prefixName = strtoupper($date . "_" . $monthText);

        $this->assertDatabaseHas('nas_folder_creation_backups', ['project_name' => $name]);

        $this->assertDatabaseHas('projects', ['name' => 'project two', 'id' => $projects[0]->id]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
