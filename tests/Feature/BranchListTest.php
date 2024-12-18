<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Company\Models\Branch;
use Tests\TestCase;

class BranchListTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function pagination_return_true(): void
    {
        $this->assertTrue(true);
    }
}
