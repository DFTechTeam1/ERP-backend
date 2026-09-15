<?php

use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Project\DetailCache;
use App\Actions\Project\FormatBoards;
use App\Enums\Production\ProjectTaskAttachment;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\assertDatabaseHas;

/**
 * These ProjectService task mutations were changed to force a FULL detail-cache
 * rebuild: each now passes forceUpdateAll = true (the 3rd argument) to
 * DetailCache::handle(), instead of the previous partial patch.
 *
 * DetailCache::handle(string $projectUid, array $necessaryUpdate = [], bool $forceUpdateAll = false)
 *   - forceUpdateAll = true clears 'detailProject{id}' before rebuilding it.
 *
 * The surrounding pipeline is stubbed so the tests isolate the two things that
 * matter per method: (1) the mutation is persisted, and (2) the cache is asked
 * to force-refresh. All three collaborators are Lorisleiva actions resolved from
 * the container, so mocking the binding intercepts every ::run()/injection:
 *   - PartialTaskPermissionCheck: returns the loaded task unchanged (skips the
 *     permission/telegram formatting formattedDetailTask() would otherwise do).
 *   - FormatBoards: returns [] (board formatting is orthogonal here).
 *   - DetailCache: asserts it receives forceUpdateAll === true, returns a stub.
 *
 * loggingTask() is already a no-op under APP_ENV=testing.
 */
function ptdcService(): ProjectService
{
    return app(ProjectService::class);
}

/**
 * Bind DetailCache so handle() must be called exactly once with the force flag on.
 * A call with force=false (i.e. a regression of this change) matches no
 * expectation and fails the test.
 */
function ptdcExpectForcedCacheRefresh(): void
{
    test()->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->withArgs(fn ($projectUid, $necessaryUpdate = [], $forceUpdateAll = false) => $forceUpdateAll === true)
            ->andReturn(['full_detail' => 'stub']);
    });
}

beforeEach(function () {
    $this->mock(PartialTaskPermissionCheck::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturnUsing(fn ($taskDetail, $telegramEmployee = null) => $taskDetail);
    });

    $this->mock(FormatBoards::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });
});

describe('storeDescription', function () {
    it('updates the task description and forces a full detail-cache rebuild', function () {
        $task = ProjectTask::factory()->create(['description' => 'Old description']);

        ptdcExpectForcedCacheRefresh();

        $response = ptdcService()->storeDescription(['description' => 'New description'], $task->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'description' => 'New description',
        ]);
    });
});

describe('updateDeadline', function () {
    it('sets the task end date and forces a full detail-cache rebuild', function () {
        $task = ProjectTask::factory()->create(['end_date' => null]);

        ptdcExpectForcedCacheRefresh();

        $response = ptdcService()->updateDeadline(
            ['end_date' => '2026-10-01 10:00:00'],
            $task->project->uid,
            $task->uid
        );

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'end_date' => '2026-10-01 10:00:00',
        ]);
    });
});

describe('updateTaskName', function () {
    it('updates the task name and forces a full detail-cache rebuild', function () {
        $task = ProjectTask::factory()->create(['name' => 'Old name']);

        ptdcExpectForcedCacheRefresh();

        $response = ptdcService()->updateTaskName(['name' => 'New name'], $task->project->uid, $task->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'name' => 'New name',
        ]);
    });
});

describe('uploadTaskAttachment', function () {
    it('stores an external link attachment and forces a full detail-cache rebuild', function () {
        $task = ProjectTask::factory()->create();

        ptdcExpectForcedCacheRefresh();

        $response = ptdcService()->uploadTaskAttachment(
            ['link' => 'https://example.com/ref', 'display_name' => 'Reference'],
            $task->uid,
            $task->project->uid
        );

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_task_attachments', [
            'project_task_id' => $task->id,
            'media' => 'https://example.com/ref',
            'display_name' => 'Reference',
            'type' => ProjectTaskAttachment::ExternalLink->value,
        ]);
    });

    it('stores a linked-task attachment and forces a full detail-cache rebuild', function () {
        $task = ProjectTask::factory()->create();
        $linkedTask = ProjectTask::factory()->create(['project_id' => $task->project_id]);

        ptdcExpectForcedCacheRefresh();

        $response = ptdcService()->uploadTaskAttachment(
            ['task_id' => [$linkedTask->uid]],
            $task->uid,
            $task->project->uid
        );

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_task_attachments', [
            'project_task_id' => $task->id,
            'media' => (string) $linkedTask->id,
            'type' => ProjectTaskAttachment::TaskLink->value,
        ]);
    });
});
