<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Services\ProjectTaskService;
use Tests\TestCase;

class GenerateTaskIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private $service;

    private $mockIdentifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProjectTaskService(new ProjectTaskRepository);
    }

    /**
     * A basic feature test example.
     */
    public function testTaskIdentifierIs4Length(): void
    {
        $this->assertEquals(4, strlen($this->service->generateIdentifier()));
    }

    public function testMassTaskIdentifierUpdate(): void
    {
        $this->service->massUpdateIdentifierID();

        $this->assertTrue(true);
    }
}
