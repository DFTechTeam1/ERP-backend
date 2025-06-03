<?php

namespace Tests\Feature\Master;

use Modules\Company\Services\MasterService;
use Tests\TestCase;

class MasterLevelStaffTest extends TestCase
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
    public function test_get_level_staff(): void
    {
        $response = $this->service->getLevelStaff();

        $this->assertArrayHasKey('error', $response);
        $this->assertFalse($response['error']);
    }
}
