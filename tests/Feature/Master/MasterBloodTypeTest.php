<?php

namespace Tests\Feature\Master;

use Modules\Company\Services\MasterService;
use Tests\TestCase;

class MasterBloodTypeTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MasterService;
    }

    /**
     * A basic feature test example.
     */
    public function test_get_all_blood_type(): void
    {
        $response = $this->service->getBloodType();

        $this->assertArrayHasKey('error', $response);
        $this->assertFalse($response['error']);
    }
}
