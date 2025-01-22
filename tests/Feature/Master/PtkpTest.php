<?php

namespace Tests\Feature\Master;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Company\Services\MasterService;
use Tests\TestCase;

class PtkpTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MasterService();
    }

    /**
     * A basic feature test example.
     */
    public function testAllPtkp(): void
    {
        $response = $this->service->getPtkpType();

        $this->assertArrayHasKey('error', $response);
        $this->assertFalse($response['error']);
    }
}
