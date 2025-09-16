<?php

use App\Enums\Development\Project\ReferenceType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Create development project without reference and pic', function () {
    $response = $this->postJson(route('api.development.projects.store'), [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
        ]);

    $this->assertDatabaseHas('development_projects', [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'created_by' => auth()->id(),
    ]);
});

it('Create development project with link reference only', function () {
    $payload = [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'references' => [
            [
                'type' => 'link',
                'link_name' => 'Test Link',
                'link' => 'https://testlink.com',
            ],
        ],
    ];

    $response = $this->postJson(route('api.development.projects.store'), $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
        ]);

    // get current development project by calling its model
    $project = \Modules\Development\Models\DevelopmentProject::where('name', 'Test Project')->first();

    $this->assertDatabaseHas('development_projects', [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'created_by' => auth()->id(),
    ]);

    // check development_project_references table, it should have not media type
    $this->assertDatabaseHas('development_project_references', [
        'development_project_id' => $project->id,
        'type' => ReferenceType::Link->value,
        'link_name' => 'Test Link',
        'link' => 'https://testlink.com',
    ]);

    $this->assertDatabaseMissing('development_project_references', [
        'development_project_id' => $project->id,
        'type' => ReferenceType::Media->value,
    ]);
});

it('Create development project with image only', function () {
    Storage::fake('public');

    // create development/projects/references on storage public path if not exists
    Storage::disk('public')->makeDirectory('development/projects/references', 0755, true);

    // image should be stored on storage path '/storage/app/public/development/projects/references'
    $response = $this->postJson(route('api.development.projects.store'), [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'references' => [
            [
                'type' => ReferenceType::Media->value,
                'image' => UploadedFile::fake()->image('test-image.jpg'),
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
        ]);

    $project = \Modules\Development\Models\DevelopmentProject::with(['references'])->where('name', 'Test Project')->first();

    // put file on storage manually using Storage facade
    Storage::disk('public')->putFileAs('development/projects/references', UploadedFile::fake()->image('test-image.jpg'), $project->references()->first()->media_path);

    $this->assertDatabaseHas('development_projects', [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'created_by' => auth()->id(),
    ]);

    $this->assertDatabaseHas('development_project_references', [
        'development_project_id' => $project->id,
        'type' => ReferenceType::Media->value,
        'media_path' => $project->references()->first()->media_path,
    ]);

    // check if 'media_path' value exists in storage
    Storage::disk('public')->assertExists('development/projects/references/'.$project->references()->first()->media_path);
});
