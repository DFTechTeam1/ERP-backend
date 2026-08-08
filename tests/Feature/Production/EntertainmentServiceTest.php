<?php

use App\Data\Production\Entertainment\BulkUpdateGroupSongData;
use App\Data\Production\Entertainment\CreateJumpBackData;
use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\SongListData;
use App\Data\Production\Entertainment\UpdateSongData;
use App\Enums\Employee\Status;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTask;
use Modules\Production\Models\EntertainmentTaskSongItem;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSong;
use Modules\Production\Models\ProjectSongItem;
use Modules\Production\Repository\ProjectSongRepository;
use Modules\Production\Services\EntertainmentService;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    // createSong() resolves the acting user via GeneralService::me(), which reads
    // Auth::id() and returns a non-nullable User. Without an authenticated user the
    // service throws and rolls back, so every createSong/list test must be authenticated.
    $this->actingAs(User::factory()->create());

    $this->service = app(EntertainmentService::class);
    $this->project = Project::factory()->create();
});

function songPayload(array $groups): CreateSongData
{
    return CreateSongData::from(['groups' => $groups]);
}

/**
 * Attach a song to a task, which is what makes it undeletable.
 *
 * EntertainmentTask::created fires a log watcher that needs an authenticated
 * employee, so the task is created without events and the uid the ModelObserver
 * would normally generate is supplied by hand.
 */
function linkSongToTask(ProjectSongItem $song, Project $project): EntertainmentTaskSongItem
{
    $task = EntertainmentTask::withoutEvents(fn () => EntertainmentTask::create([
        'uid' => Str::uuid()->toString(),
        'project_id' => $project->id,
        'name' => "Task for {$song->song_name}",
    ]));

    return EntertainmentTaskSongItem::create([
        'entertainment_task_id' => $task->id,
        'song_item_id' => $song->id,
    ]);
}

describe('getSongListCacheKey', function () {
    it('builds a project-scoped cache key', function () {
        expect($this->service->getSongListCacheKey('abc-123'))
            ->toBe('project_song_list_abc-123');
    });
});

describe('createSong', function () {
    it('persists a group and its songs for the project', function () {
        $payload = songPayload([
            ['name' => 'Ballads', 'songs' => ['Song A', 'Song B']],
        ]);

        $response = $this->service->createSong($payload, $this->project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success create song')
            ->and($response['code'])->toBe(201);

        assertDatabaseHas('project_songs', [
            'project_id' => $this->project->id,
            'group_name' => 'Ballads',
        ]);
        assertDatabaseHas('project_song_items', ['song_name' => 'Song A']);
        assertDatabaseHas('project_song_items', ['song_name' => 'Song B']);
        assertDatabaseCount('project_song_items', 2);
    });

    it('auto-generates a uid for every stored row', function () {
        $this->service->createSong(
            songPayload([['name' => 'Group 1', 'songs' => ['Only Song']]]),
            $this->project->uid,
        );

        $song = ProjectSongItem::where('song_name', 'Only Song')->first();
        $songGroup = ProjectSong::where('group_name', 'Group 1')->first();

        expect($songGroup->uid)->not->toBeEmpty()
            ->and($song->uid)->not->toBeEmpty()
            ->and($song->project_song_id)->toBe($songGroup->id);
    });

    it('creates multiple groups in a single call', function () {
        $payload = songPayload([
            ['name' => 'Opening', 'songs' => ['Intro']],
            ['name' => 'Main Show', 'songs' => ['Track 1', 'Track 2', 'Track 3']],
        ]);

        $response = $this->service->createSong($payload, $this->project->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseCount('project_songs', 2);
        assertDatabaseCount('project_song_items', 4);
    });

    it('deduplicates identical songs within the same group via upsert', function () {
        $payload = songPayload([
            ['name' => 'Encore', 'songs' => ['Same Song', 'Same Song']],
        ]);

        $this->service->createSong($payload, $this->project->uid);

        assertDatabaseCount('project_song_items', 1);
    });

    it('allows the same song title to exist in different groups', function () {
        $payload = songPayload([
            ['name' => 'Set A', 'songs' => ['Shared Title']],
            ['name' => 'Set B', 'songs' => ['Shared Title']],
        ]);

        $response = $this->service->createSong($payload, $this->project->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseCount('project_songs', 2);
        assertDatabaseCount('project_song_items', 2);
    });

    it('rolls back and returns an error when the project does not exist', function () {
        $payload = songPayload([
            ['name' => 'Orphan', 'songs' => ['Ghost Song']],
        ]);

        $response = $this->service->createSong($payload, 'non-existent-uid');

        expect($response['error'])->toBeTrue();
        assertDatabaseMissing('project_songs', ['group_name' => 'Orphan']);
        assertDatabaseCount('project_song_items', 0);
    });
});

describe('list', function () {
    it('returns the stored songs as SongListData with an unassigned status', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set List', 'songs' => ['First', 'Second']]]),
            $this->project->uid,
        );

        $response = $this->service->list($this->project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['code'])->toBe(201)
            ->and($response['data'])->toHaveCount(2)
            ->and($response['data'][0])->toBeInstanceOf(SongListData::class);

        $names = collect($response['data'])->pluck('name')->all();
        expect($names)->toContain('First', 'Second');

        expect($response['data'][0]->group)->toBe('Set List')
            ->and($response['data'][0]->status_color)->toBe('grey');
    });

    it('caches the result under the project cache key', function () {
        $this->service->createSong(
            songPayload([['name' => 'Cached', 'songs' => ['Song']]]),
            $this->project->uid,
        );

        $key = $this->service->getSongListCacheKey($this->project->uid);
        expect(Cache::has($key))->toBeFalse();

        $this->service->list($this->project->uid);

        expect(Cache::has($key))->toBeTrue();
    });

    it('returns an empty list for a project that has no songs', function () {
        $response = $this->service->list($this->project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['code'])->toBe(201)
            ->and($response['data'])->toBe([]);
    });
});

describe('updateSong', function () {
    it('updates the song name in the database', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['Old Name']]]),
            $this->project->uid,
        );
        $song = ProjectSongItem::where('song_name', 'Old Name')->first();

        $response = $this->service->updateSong(
            UpdateSongData::from(['song' => 'New Name']),
            $this->project->uid,
            $song->uid,
        );

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success update song');

        assertDatabaseHas('project_song_items', ['song_name' => 'New Name']);
        assertDatabaseMissing('project_song_items', ['song_name' => 'Old Name']);
    });

    it('returns an error when the song does not exist', function () {
        $response = $this->service->updateSong(
            UpdateSongData::from(['song' => 'Whatever']),
            $this->project->uid,
            'non-existent-uid',
        );

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe('Song not found');
    });

    /**
     * Regression: the cache holds SongListData objects. A previous bug rewrote it
     * as plain arrays (via Collection::toArray()), so a SECOND mutation would read
     * $song->uid on an array and throw. The two consecutive updates below guard it.
     */
    it('renames the matching cached entry and survives consecutive updates', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['First', 'Second']]]),
            $this->project->uid,
        );

        // Warm the cache as an array of SongListData objects.
        $this->service->list($this->project->uid);

        $first = ProjectSongItem::where('song_name', 'First')->first();
        $second = ProjectSongItem::where('song_name', 'Second')->first();

        $this->service->updateSong(UpdateSongData::from(['song' => 'First Updated']), $this->project->uid, $first->uid);
        $resp = $this->service->updateSong(UpdateSongData::from(['song' => 'Second Updated']), $this->project->uid, $second->uid);

        expect($resp['error'])->toBeFalse();

        $cached = $this->service->list($this->project->uid)['data'];

        expect($cached)->toHaveCount(2)
            ->and($cached[0])->toBeInstanceOf(SongListData::class);

        $names = collect($cached)->pluck('name')->all();
        expect($names)->toContain('First Updated', 'Second Updated')
            ->and($names)->not->toContain('First', 'Second');
    });
});

describe('deleteSingleSong', function () {
    beforeEach(function () {
        $this->songRepo = app(ProjectSongRepository::class);

        $this->makeGroup = function (Project $project, string $name, array $songs) {
            $group = $this->songRepo->store([
                'project_id' => $project->id,
                'group_name' => $name,
            ]);

            if ($songs) {
                $this->songRepo->storeSongs(groupId: $group->id, songs: $songs);
            }

            return $group->refresh();
        };

        $this->group = ($this->makeGroup)($this->project, 'Set', ['Doomed', 'Survivor']);
        $this->sibling = ($this->makeGroup)($this->project, 'Other', ['Elsewhere']);

        $this->otherProject = Project::factory()->create();
        $this->foreignGroup = ($this->makeGroup)($this->otherProject, 'Foreign', ['Not Mine']);

        $this->songByName = fn (string $name) => ProjectSongItem::where('song_name', $name)->firstOrFail();

        $this->deleteSingle = fn (string $songUid, ?string $groupUid = null, ?string $projectUid = null) => $this->service->deleteSingleSong(
            $projectUid ?? $this->project->uid,
            $groupUid ?? $this->group->uid,
            $songUid,
        );
    });

    it('deletes the song from the database', function () {
        $doomed = ($this->songByName)('Doomed');

        $response = ($this->deleteSingle)($doomed->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success delete song');

        assertDatabaseMissing('project_song_items', ['song_name' => 'Doomed']);
    });

    it('leaves the other songs in the same group alone', function () {
        ($this->deleteSingle)(($this->songByName)('Doomed')->uid);

        assertDatabaseHas('project_song_items', ['song_name' => 'Survivor']);
    });

    it('leaves songs in other groups alone', function () {
        ($this->deleteSingle)(($this->songByName)('Doomed')->uid);

        assertDatabaseHas('project_song_items', ['song_name' => 'Elsewhere']);
        assertDatabaseHas('project_song_items', ['song_name' => 'Not Mine']);
    });

    it('errors when the project does not exist', function () {
        $doomed = ($this->songByName)('Doomed');

        $response = ($this->deleteSingle)($doomed->uid, null, 'non-existent-uid');

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Project not found');

        assertDatabaseHas('project_song_items', ['song_name' => 'Doomed']);
    });

    it('errors when the group does not exist', function () {
        $doomed = ($this->songByName)('Doomed');

        $response = ($this->deleteSingle)($doomed->uid, 'non-existent-uid');

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Group not found');

        assertDatabaseHas('project_song_items', ['song_name' => 'Doomed']);
    });

    /**
     * getGroupSong() scopes the lookup by project_id, so a real group uid belonging
     * to a different project must not resolve.
     */
    it('refuses a group that belongs to another project', function () {
        $notMine = ($this->songByName)('Not Mine');

        $response = ($this->deleteSingle)($notMine->uid, $this->foreignGroup->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Group not found');

        assertDatabaseHas('project_song_items', ['song_name' => 'Not Mine']);
    });

    /**
     * The delete is scoped by project_song_id as well as uid, so a song uid from a
     * different group cannot be deleted by naming the wrong group.
     */
    it('does not delete a song that belongs to a different group', function () {
        $elsewhere = ($this->songByName)('Elsewhere');

        // Real song uid, real group uid, but they do not belong together.
        $response = ($this->deleteSingle)($elsewhere->uid, $this->group->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_song_items', ['song_name' => 'Elsewhere']);
    });

    it('drops the song from the cached list', function () {
        $doomed = ($this->songByName)('Doomed');

        // Warm the cache with both songs present.
        expect(collect($this->service->list($this->project->uid)['data'])->pluck('name'))
            ->toContain('Doomed');

        ($this->deleteSingle)($doomed->uid);

        expect(collect($this->service->list($this->project->uid)['data'])->pluck('name'))
            ->not->toContain('Doomed')
            ->and(collect($this->service->list($this->project->uid)['data'])->pluck('name'))
            ->toContain('Survivor');
    });

    /**
     * Regression: the cache holds SongListData objects. A previous bug rewrote it as
     * plain arrays, so a SECOND delete threw "Attempt to read property uid on array".
     */
    it('survives consecutive deletes against a warm cache', function () {
        $doomed = ($this->songByName)('Doomed');
        $survivor = ($this->songByName)('Survivor');

        expect($this->service->list($this->project->uid)['data'])->toHaveCount(2);

        ($this->deleteSingle)($doomed->uid);
        $second = ($this->deleteSingle)($survivor->uid);

        expect($second['error'])->toBeFalse();

        $cached = $this->service->list($this->project->uid)['data'];
        expect($cached)->toBeEmpty();
    });

    /**
     * Documents CURRENT behaviour, which is a known gap: deleteSingleSong still
     * carries a `TODO: validate active task`, and entertainment_task_song_items
     * cascades on delete - so removing a song silently detaches it from its task.
     * The removed deleteSong() used to refuse this outright.
     */
    it('currently deletes a song even when it is assigned to a task', function () {
        $doomed = ($this->songByName)('Doomed');
        linkSongToTask($doomed, $this->project);

        assertDatabaseHas('entertainment_task_song_items', ['song_item_id' => $doomed->id]);

        $response = ($this->deleteSingle)($doomed->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseMissing('project_song_items', ['song_name' => 'Doomed']);
        assertDatabaseMissing('entertainment_task_song_items', ['song_item_id' => $doomed->id]);
    });

    /**
     * Documents CURRENT behaviour: deleteWhere() matching nothing is not treated as
     * an error, so an unknown song uid still reports success.
     */
    it('currently reports success for a song uid that does not exist', function () {
        $response = ($this->deleteSingle)('non-existent-uid');

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success delete song');

        assertDatabaseCount('project_song_items', 4);
    });
});

describe('createJumpBackTask', function () {
    beforeEach(function () {
        // EntertainmentLogWatch fires on EntertainmentTask::created and resolves the
        // acting user's employee record to compose the log line, so the authenticated
        // user must be linked to an employee.
        Employee::factory()->create([
            'user_id' => auth()->id(),
            'status' => Status::Permanent->value,
        ]);

        $this->assignee = Employee::factory()->create(['status' => Status::Permanent->value]);
    });

    function jumpBackPayload(array $songUids, array $assigneeUids): CreateJumpBackData
    {
        return CreateJumpBackData::from([
            'assignee_uids' => $assigneeUids,
            'due' => now()->addWeek()->format('Y-m-d H:i:s'),
            'name' => 'Jump Back Batch 1',
            'note' => null,
            'song_uids' => $songUids,
        ]);
    }

    it('creates the task and links every requested song', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['A', 'B']]]),
            $this->project->uid,
        );
        $songs = ProjectSongItem::whereIn('song_name', ['A', 'B'])->get();

        $response = $this->service->createJumpBackTask(
            jumpBackPayload($songs->pluck('uid')->all(), [$this->assignee->uid]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse();

        $task = EntertainmentTask::where('project_id', $this->project->id)->first();

        expect($task)->not->toBeNull()
            ->and($task->name)->toBe('Jump Back Batch 1');

        foreach ($songs as $song) {
            assertDatabaseHas('entertainment_task_song_items', [
                'entertainment_task_id' => $task->id,
                'song_item_id' => $song->id,
            ]);
        }
    });

    it('rolls back when one of the song uids does not exist', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['A']]]),
            $this->project->uid,
        );
        $song = ProjectSongItem::where('song_name', 'A')->first();

        $response = $this->service->createJumpBackTask(
            jumpBackPayload([$song->uid, 'non-existent-uid'], [$this->assignee->uid]),
            $this->project->uid,
        );

        // errorResponse() decorates thrown-exception messages with file/line context.
        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Song not found.');

        assertDatabaseCount('entertainment_tasks', 0);
    });
});

describe('bulkUpdateGroupSong', function () {
    beforeEach(function () {
        $this->songRepo = app(ProjectSongRepository::class);

        $this->makeGroup = function (Project $project, string $name, array $songs) {
            $group = $this->songRepo->store([
                'project_id' => $project->id,
                'group_name' => $name,
            ]);

            if ($songs) {
                $this->songRepo->storeSongs(groupId: $group->id, songs: $songs);
            }

            return $group->refresh();
        };

        // Two groups on the target project, plus a group on a SECOND project. The
        // decoy groups exist so every assertion below can prove the update landed on
        // the requested group only.
        $this->target = ($this->makeGroup)($this->project, 'Opening', ['Intro', 'Warm Up']);
        $this->sibling = ($this->makeGroup)($this->project, 'Closing', ['Finale']);

        $this->otherProject = Project::factory()->create();
        $this->foreign = ($this->makeGroup)($this->otherProject, 'Foreign', ['Not Mine']);

        $this->songByName = fn (string $name) => ProjectSongItem::where('song_name', $name)->firstOrFail();
    });

    function bulkPayload(array $overrides = []): BulkUpdateGroupSongData
    {
        return BulkUpdateGroupSongData::from($overrides + [
            'deleted' => [],
            'group_uid' => '',
            'from' => 'song-list',
            'name' => 'Untitled',
            'songs' => [],
        ]);
    }

    it('renames the group it was given', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success');

        expect($this->target->fresh()->group_name)->toBe('Opening Renamed');
    });

    it('leaves every other group untouched', function () {
        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect($this->sibling->fresh()->group_name)->toBe('Closing')
            ->and($this->foreign->fresh()->group_name)->toBe('Foreign');
    });

    it('renames the songs it was given', function () {
        $intro = ($this->songByName)('Intro');

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening',
                'songs' => [['name' => 'Intro Renamed', 'uid' => $intro->uid]],
            ]),
            $this->project->uid,
        );

        expect($intro->fresh()->song_name)->toBe('Intro Renamed');
    });

    it('leaves songs that were not listed alone', function () {
        $intro = ($this->songByName)('Intro');

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening',
                'songs' => [['name' => 'Intro Renamed', 'uid' => $intro->uid]],
            ]),
            $this->project->uid,
        );

        assertDatabaseHas('project_song_items', ['song_name' => 'Warm Up']);
        assertDatabaseHas('project_song_items', ['song_name' => 'Finale']);
    });

    it('renames several songs in a single call', function () {
        $intro = ($this->songByName)('Intro');
        $warmUp = ($this->songByName)('Warm Up');

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening',
                'songs' => [
                    ['name' => 'First', 'uid' => $intro->uid],
                    ['name' => 'Second', 'uid' => $warmUp->uid],
                ],
            ]),
            $this->project->uid,
        );

        expect($intro->fresh()->song_name)->toBe('First')
            ->and($warmUp->fresh()->song_name)->toBe('Second');
    });

    it('skips a song uid that does not exist instead of failing', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
                'songs' => [['name' => 'Ghost', 'uid' => 'non-existent-uid']],
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse()
            ->and($this->target->fresh()->group_name)->toBe('Opening Renamed');

        assertDatabaseMissing('project_song_items', ['song_name' => 'Ghost']);
    });

    it('errors and changes nothing when the project does not exist', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            'non-existent-uid',
        );

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Project not found')
            ->and($this->target->fresh()->group_name)->toBe('Opening');
    });

    it('errors and changes nothing when the group does not exist', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => 'non-existent-uid',
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Group not found');

        // Nothing anywhere may be renamed by a lookup that found no group.
        expect($this->target->fresh()->group_name)->toBe('Opening')
            ->and($this->sibling->fresh()->group_name)->toBe('Closing')
            ->and($this->foreign->fresh()->group_name)->toBe('Foreign');
    });

    /**
     * Regression: the group lookup used to pass `'uid' => ...` to the repository.
     * BaseRepository has no `uid` param, so the key was silently dropped and show()
     * returned the FIRST project_songs row in the table - meaning any group_uid
     * renamed an arbitrary group, potentially on someone else's project.
     */
    it('targets the group by uid rather than the first row in the table', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                // Deliberately NOT the first group created in this test's fixtures.
                'group_uid' => $this->sibling->uid,
                'name' => 'Closing Renamed',
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse()
            ->and($this->sibling->fresh()->group_name)->toBe('Closing Renamed')
            ->and($this->target->fresh()->group_name)->toBe('Opening');
    });

    it('forgets the cached song list so the next read is rebuilt', function () {
        $key = $this->service->getSongListCacheKey($this->project->uid);
        $this->service->list($this->project->uid);
        expect(Cache::has($key))->toBeTrue();

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect(Cache::has($key))->toBeFalse();
    });

    it('serves renamed songs on the next list instead of the stale cache', function () {
        $intro = ($this->songByName)('Intro');

        // Warm the cache with the pre-update names.
        expect(collect($this->service->list($this->project->uid)['data'])->pluck('name'))
            ->toContain('Intro');

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
                'songs' => [['name' => 'Intro Renamed', 'uid' => $intro->uid]],
            ]),
            $this->project->uid,
        );

        $names = collect($this->service->list($this->project->uid)['data'])->pluck('name');

        expect($names)->toContain('Intro Renamed')
            ->and($names)->not->toContain('Intro');
    });

    it('reflects the new group name on the next list', function () {
        $this->service->list($this->project->uid);

        $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect(collect($this->service->list($this->project->uid)['data'])->pluck('group'))
            ->toContain('Opening Renamed');
    });

    it('keeps the cache intact when the call fails', function () {
        $key = $this->service->getSongListCacheKey($this->project->uid);
        $this->service->list($this->project->uid);

        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => 'non-existent-uid',
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        // Nothing was committed, so there is nothing to invalidate.
        expect($response['error'])->toBeTrue()
            ->and(Cache::has($key))->toBeTrue();
    });

    /**
     * BulkUpdateGroupSongData still carries a `deleted` array, but the service no
     * longer acts on it - deletion moved to deleteSingleSong(). Pinned here because
     * a client that still sends `deleted` gets a silent no-op, not an error.
     */
    it('ignores the deleted field, which is now deleteSingleSong territory', function () {
        $warmUp = ($this->songByName)('Warm Up');

        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'deleted' => [$warmUp->uid],
                'group_uid' => $this->target->uid,
                'name' => 'Opening',
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_song_items', ['song_name' => 'Warm Up']);
    });

    it('handles an empty payload as a plain group rename', function () {
        $response = $this->service->bulkUpdateGroupSong(
            bulkPayload([
                'group_uid' => $this->target->uid,
                'name' => 'Opening Renamed',
            ]),
            $this->project->uid,
        );

        expect($response['error'])->toBeFalse()
            ->and($this->target->fresh()->group_name)->toBe('Opening Renamed');

        assertDatabaseCount('project_song_items', 4);
    });
});
