<?php

namespace Tests\Feature\Project;

use App\Services\GeneralService;
use App\Traits\HasProjectConstructor;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Hrd\Models\Employee;
use Modules\Production\Exceptions\SongNotFound;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Services\ProjectService;
use Tests\TestCase;

class DistributeSongTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication, HasProjectConstructor;

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
    public function testDistributeWithDeletedSong(): void
    {
        $mockGeneral = $this->instance(
            abstract: GeneralService::class,
            instance: Mockery::mock(GeneralService::class)
        );

        $mockSongRepo = $this->instance(
            abstract: ProjectSongListRepository::class,
            instance: Mockery::mock(ProjectSongListRepository::class)
        );

        $this->setProjectConstructor(
            generalService: $mockGeneral,
            projectSongListRepo: $mockSongRepo
        );

        $mockGeneral->shouldReceive('getIdFromUid')
            ->atMost(1)
            ->withArgs(function ($uid, $employee) {
                return $uid === 'uid' && $employee instanceof Employee;
            })
            ->andReturn(1)
            ->shouldReceive('getIdFromUid')
            ->atMost(1)
            ->withArgs(function ($uid, $projectSong) {
                return $uid === 'id' && $projectSong instanceof ProjectSongList;
            })
            ->andReturn(1);

        $mockSongRepo->shouldReceive('show')
            ->atMost(1)
            ->with('id', 'id')
            ->andReturnNull();

        $response = $this->projectService->distributeSong(['employee_uid' => 'uid'], 'uid', 'id');

        $this->assertTrue($response['error']);
    }

    public function testDistributeSongSuccess(): void
    {
        $payload = [
            'employee_id' => 'id'
        ];

        
    }
}
