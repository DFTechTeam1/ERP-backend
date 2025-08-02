<?php

namespace Modules\Company\Tests\Unit\Branch;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Company\Models\Branch;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function default_pagination_return_true(): void
    {
        // Create 25 Branch records
        Branch::factory()->count(25)->create();

        // Send GET request for paginated results
        $response = $this->getJson('/api/branch?page=1&itemsPerPage=10');

        // Assert the status code
        $response->assertStatus(201);

        // Assert that the response contains the paginated data (itemsPerPage = 10)
        $response->assertJsonCount(10, 'data');
    }
}
