<?php

namespace Tests\Feature\Master;

use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Company\Models\Branch;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    private $service;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $userData = $this->auth();
        $this->user = $userData['user'];

        Sanctum::actingAs($this->user);
        $this->actingAs($this->user);

    }

    /**
     * A basic feature test example.
     */
    public function test_missing_payload_on_create(): void
    {
        $payload = [
            'name' => '',
            'short_name' => '',
        ];

        $response = $this->postJson(route('api.company.branches.store'), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(422);
    }

    public function test_unique_name_validation(): void
    {
        Branch::factory()->count(1)->create([
            'name' => 'branch 1',
        ]);

        $payload = [
            'name' => 'branch 1',
            'short_name' => 'branch1',
        ];

        $response = $this->postJson(route('api.company.branches.store'), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'name',
            ],
        ]);
        $this->assertStringContainsString('The name has already been taken.', $response['errors']['name'][0]);
    }

    public function test_success_save_new_branch(): void
    {
        $payload = [
            'name' => 'branch',
            'short_name' => 'branch',
        ];

        $response = $this->postJson(route('api.company.branches.store'), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('branches', ['name' => 'branch']);
    }

    public function test_update_failed_on_unique_name(): void
    {
        $branches = Branch::factory()->count(2)->create();

        $payload = [
            'name' => $branches[1]->name,
            'short_name' => 'branch_updated',
        ];

        $response = $this->putJson(route('api.company.branches.update', ['branch' => $branches[0]->id]), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'name',
            ],
        ]);
        $this->assertStringContainsString('exists', $response['errors']['name'][0]);
    }

    public function test_success_update_branch(): void
    {
        $branches = Branch::factory()->count(1)->create();

        $payload = [
            'name' => 'updated branch',
            'short_name' => 'branch_updated',
        ];

        $response = $this->putJson(route('api.company.branches.update', ['branch' => $branches[0]->id]), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('branches', ['name' => 'updated branch']);
    }

    public function test_bulk_delete_branch(): void
    {
        $branches = Branch::factory()->count(1)->create(['name' => 'new']);

        $ids = collect($branches)->map(function ($item) {
            return [
                'uid' => $item->id,
            ];
        })->values()->toArray();

        $payload = [
            'ids' => $ids,
        ];

        $response = $this->postJson(route('api.company.branches.bulk-delete'), $payload, [
            'Authorization' => 'Bearer '.$this->getToken($this->user),
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseMissing('branches', ['name' => 'new']);
    }

    public function test_get_all_branches(): void
    {
        Branch::factory()->count(5)->create();

        $response = $this->getJson(route('api.company.branches.get-all'));

        $response->assertStatus(201);
        $this->assertDatabaseCount('branches', 6);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
