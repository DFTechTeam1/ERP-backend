<?php

use Carbon\Carbon;
use Modules\Development\app\Services\DevelopmentProjectCacheService;
use Modules\Development\Models\DevelopmentProject;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

// after all test is done, delete cache
afterEach(function () {
    $service = app(DevelopmentProjectCacheService::class);
    $service->invalidateAllProjectCaches();
});

it('Update project return success with updating current cache', function () {
    DevelopmentProject::factory()->count(5)->create();

    $project = DevelopmentProject::factory()->withPics()->create();

    // store cache with call function inside developmentProjectCacheService class
    $service = app(DevelopmentProjectCacheService::class);
    $currentCaches = $service->storeAllProjectListToCache();

    // validate cache
    expect($currentCaches)->toBeArray()->not->toBeEmpty();

    // validate length of cached projects
    expect($currentCaches)->toHaveLength(6);

    $payload = [
        'name' => 'updated name',
        'project_date' => Carbon::parse($project->project_date)->addDays(2)->format('Y-m-d')
    ];

    $response = putJson(route('api.development.projects.update', ['id' => $project->uid]), $payload);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('development_projects', $payload);

    // check cache
    $updatedCaches = $service->storeAllProjectListToCache();

    // search index
    $index = collect($updatedCaches)->search(fn ($item) => $item['uid'] === $project->uid);

    expect($updatedCaches)->toBeArray()->not->toBeEmpty();
    expect($updatedCaches)->toHaveLength(6);
    expect($updatedCaches[$index]['name'])->toBe($payload['name']);
    expect($updatedCaches[$index]['project_date'])->toBe($payload['project_date']);
});
