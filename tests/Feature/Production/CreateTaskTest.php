<?php

// api.production.storeTask

use Modules\Production\Models\Project;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true
    );

    $this->actingAs($this->user);

    $this->project = Project::factory()
        ->withBoards()
        ->create();
});

it ("Create task with missing parameters", function () {
    $response = $this->postJson(route('api.production.storeTask', [
        'boardId' => $this->project->boards->first()->id
    ]), [
        // missing parameters
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'name'
    ]);
});

it ('Create task without pic', function () {
    $response = $this->postJson(route('api.production.storeTask', [
        'boardId' => $this->project->boards->first()->id
    ]), [
        'name' => 'New Task',
        'end_date' => now()->addDays(7)->toDateString(),
        // no pic
    ]);
});