<?php

namespace Tests\Feature\Project;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Modules\Production\Models\Project;
use Tests\TestCase;

class StoreReferenceTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    private $user;

    private $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        $this->user = $userData['user'];
        $this->employee = $userData['employee'];

        Sanctum::actingAs($this->user);
        $this->actingAs($this->user);
    }

    /**
     * A basic feature test example.
     */
    public function testPayloadIsMissing(): void
    {
        $project = Project::factory()->count(1)->create();
        dd($project);
        $payload = [
            'link' => [
                ['href' => 'google.com']
            ]
        ];

        $response = $this->post(route('api.production.store-reference', ['id' => $project[0]->uid]), $payload, [
            'Authorization' => 'Bearer ' . $this->getToken($this->user)
        ]);

        dd($response);
    }
}
