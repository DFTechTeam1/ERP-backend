<?php

namespace Tests\Feature\Master;

use Modules\Company\Services\MasterService;
use Tests\TestCase;

class SalaryTypeTest extends TestCase
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
    public function test_all_salary_type(): void
    {
        $response = $this->service->getSalaryType();

        $this->assertArrayHasKey('error', $response);
        $this->assertFalse($response['error']);
    }
}
