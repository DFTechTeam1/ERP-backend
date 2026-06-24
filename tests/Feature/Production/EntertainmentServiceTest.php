<?php

use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\SongListData;
use App\Data\Production\Entertainment\UpdateSongData;
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

describe('deleteSong', function () {
    it('deletes the song from the database', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['Doomed']]]),
            $this->project->uid,
        );
        $song = ProjectSongItem::where('song_name', 'Doomed')->first();

        $response = $this->service->deleteSong($this->project->uid, $song->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success delete song list');

        assertDatabaseMissing('project_song_items', ['song_name' => 'Doomed']);
    });

    it('returns an error when the song does not exist', function () {
        $response = $this->service->deleteSong($this->project->uid, 'non-existent-uid');

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe('Song not found');
    });

    /**
     * Regression for the reported bug: deleting one song succeeded but a second
     * delete threw "Attempt to read property uid on array" because the cache had
     * been rewritten as plain arrays. These consecutive deletes guard against it.
     */
    it('removes songs from the cached list across consecutive deletes', function () {
        $this->service->createSong(
            songPayload([['name' => 'Set', 'songs' => ['A', 'B', 'C']]]),
            $this->project->uid,
        );

        // Warm the cache as an array of SongListData objects.
        expect($this->service->list($this->project->uid)['data'])->toHaveCount(3);

        $a = ProjectSongItem::where('song_name', 'A')->first();
        $b = ProjectSongItem::where('song_name', 'B')->first();

        $this->service->deleteSong($this->project->uid, $a->uid);
        $second = $this->service->deleteSong($this->project->uid, $b->uid);

        expect($second['error'])->toBeFalse();

        $cached = $this->service->list($this->project->uid)['data'];

        expect($cached)->toHaveCount(1)
            ->and($cached[0])->toBeInstanceOf(SongListData::class)
            ->and($cached[0]->name)->toBe('C');
    });
});
