<?php

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it ('Create development project without reference and pic', function () {
    $response = $this->postJson(route('api.development.projects.store'), [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('development_projects', [
        'name' => 'Test Project',
        'description' => 'This is a test project.',
        'project_date' => '2023-10-01',
        'created_by' => auth()->id(),
    ]);
});