<?php

use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\SongListData;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSong;
use Modules\Production\Models\ProjectSongItem;
use Modules\Production\Services\EntertainmentService;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(EntertainmentService::class);
    $this->project = Project::factory()->create();
});

function songPayload(array $groups): CreateSongData
{
    return CreateSongData::from(['groups' => $groups]);
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

    /**
     * NOTE: documents CURRENT behavior, which is arguably a bug. When a project
     * has no song groups, cacheSongList() receives a null ProjectSong from
     * show() and dereferences $data->items, so list() surfaces an error instead
     * of an empty list. If the service is fixed to return [] for empty projects,
     * flip this expectation.
     */
    it('returns an error for a project that has no songs (current behavior)', function () {
        $response = $this->service->list($this->project->uid);

        expect($response['error'])->toBeTrue();
    });
});
