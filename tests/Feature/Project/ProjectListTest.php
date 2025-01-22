<?php

namespace Tests\Feature\Project;

use App\Traits\HasEmployeeConstructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Modules\Production\Services\TestingService;
use Tests\TestCase;

class ProjectListTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
    }
}
