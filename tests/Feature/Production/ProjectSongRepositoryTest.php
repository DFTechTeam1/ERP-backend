<?php

use App\Repository\BaseRepository;
use Illuminate\Support\Str;
use Modules\Production\Models\EntertainmentTask;
use Modules\Production\Models\EntertainmentTaskSongItem;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongItem;
use Modules\Production\Repository\ProjectSongItemRepository;
use Modules\Production\Repository\ProjectSongRepository;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->repo = app(ProjectSongRepository::class);
    $this->project = Project::factory()->create();

    // Three groups with a different number of songs each, so the `has` count
    // thresholds below have a clear boundary to land on.
    $this->makeGroup = function (string $name, array $songs) {
        $group = $this->repo->store([
            'project_id' => $this->project->id,
            'group_name' => $name,
        ]);

        if ($songs) {
            $this->repo->storeSongs(groupId: $group->id, songs: $songs);
        }

        return $group;
    };

    $this->empty = ($this->makeGroup)('Empty', []);
    $this->single = ($this->makeGroup)('Single', ['A']);
    $this->triple = ($this->makeGroup)('Triple', ['B', 'C', 'D']);

    $this->allIds = [$this->empty->id, $this->single->id, $this->triple->id];

    $this->idsMatching = function (array $params) {
        return $this->repo->get([
            'select' => ['id'],
            'whereIn' => ['id' => $this->allIds],
            'orderBy' => ['id' => 'asc'],
        ] + $params)->pluck('id')->all();
    };
});

it('is backed by the shared base repository', function () {
    expect($this->repo)->toBeInstanceOf(BaseRepository::class);
});

describe('has', function () {
    it('keeps only rows owning at least one related record when given a relation name', function () {
        expect(($this->idsMatching)(['has' => ['items']]))
            ->toBe([$this->single->id, $this->triple->id]);
    });

    it('treats a relation mapped to null the same as a plain existence check', function () {
        expect(($this->idsMatching)(['has' => ['items' => null]]))
            ->toBe([$this->single->id, $this->triple->id]);
    });

    it('reads an integer value as a minimum count', function () {
        expect(($this->idsMatching)(['has' => ['items' => 3]]))
            ->toBe([$this->triple->id]);

        expect(($this->idsMatching)(['has' => ['items' => 4]]))
            ->toBeEmpty();
    });

    it('reads an [operator, count] pair as an explicit comparison', function () {
        expect(($this->idsMatching)(['has' => ['items' => ['>', 3]]]))
            ->toBeEmpty();

        // A `<` threshold is satisfied by a zero count too, so the empty group counts.
        expect(($this->idsMatching)(['has' => ['items' => ['<', 3]]]))
            ->toBe([$this->empty->id, $this->single->id]);

        expect(($this->idsMatching)(['has' => ['items' => ['=', 1]]]))
            ->toBe([$this->single->id]);
    });

    it('mixes a plain existence check and a count threshold in one array', function () {
        expect(($this->idsMatching)([
            'has' => ['project', 'items' => 3],
        ]))->toBe([$this->triple->id]);
    });

    it('combines with the other params instead of replacing them', function () {
        expect($this->repo->get([
            'select' => ['id'],
            'where' => ['group_name' => 'Single'],
            'has' => ['items' => 3],
        ]))->toBeEmpty();
    });

    it('narrows a paginated result set', function () {
        $paginated = $this->repo->paginate([
            'select' => ['id'],
            'whereIn' => ['id' => $this->allIds],
            'has' => ['items'],
            'orderBy' => ['id' => 'asc'],
        ], 1);

        expect($paginated->total())->toBe(2)
            ->and($paginated->getCollection())->toHaveCount(1)
            ->and($paginated->getCollection()->first()->id)->toBe($this->single->id);
    });

    it('eager-loads through with while filtering through has', function () {
        $records = $this->repo->get([
            'select' => ['id'],
            'whereIn' => ['id' => $this->allIds],
            'with' => ['items:id,project_song_id,song_name'],
            'has' => ['items' => 3],
        ]);

        expect($records)->toHaveCount(1)
            ->and($records->first()->items)->toHaveCount(3);
    });
});

describe('doesntHave', function () {
    it('keeps only rows owning no related record', function () {
        expect(($this->idsMatching)(['doesntHave' => ['items']]))
            ->toBe([$this->empty->id]);
    });
});

/**
 * ProjectSongItemRepository::deleteWhere() is a SINGLE-record delete: it resolves
 * one row through show() and deletes the model, returning bool - not a mass delete.
 * It backs EntertainmentService::deleteSingleSong().
 */
describe('deleteWhere', function () {
    beforeEach(function () {
        $this->itemRepo = app(ProjectSongItemRepository::class);
    });

    it('deletes the matching row and reports true', function () {
        $song = ProjectSongItem::where('song_name', 'A')->firstOrFail();

        expect($this->itemRepo->deleteWhere(['uid' => $song->uid]))->toBeTrue();

        assertDatabaseMissing('project_song_items', ['song_name' => 'A']);
        assertDatabaseCount('project_song_items', 3);
    });

    it('reports false and deletes nothing when no row matches', function () {
        expect($this->itemRepo->deleteWhere(['uid' => 'non-existent-uid']))->toBeFalse();

        assertDatabaseCount('project_song_items', 4);
    });

    it('ands the conditions together rather than or-ing them', function () {
        $song = ProjectSongItem::where('song_name', 'A')->firstOrFail();

        // Right uid, wrong group - the pair must not resolve.
        expect($this->itemRepo->deleteWhere([
            'uid' => $song->uid,
            'project_song_id' => $this->triple->id,
        ]))->toBeFalse();

        assertDatabaseHas('project_song_items', ['song_name' => 'A']);
    });

    /**
     * show() takes the first match, so a broad condition removes ONE row, not all of
     * them. Callers that mean "delete them all" cannot use this method.
     */
    it('deletes only one row even when several match', function () {
        expect($this->itemRepo->deleteWhere(['project_song_id' => $this->triple->id]))->toBeTrue();

        expect(ProjectSongItem::where('project_song_id', $this->triple->id)->count())->toBe(2);
    });
});

/**
 * Eloquent's has() routes any dotted relation through hasNested(), so the
 * repository can pass 'items.entertainmentTaskSongItems' straight through with
 * no extra handling. The catch worth pinning down: every segment but the LAST
 * becomes a plain whereHas existence check, and the operator/count applies only
 * to the innermost segment.
 */
describe('has with a nested relation path', function () {
    beforeEach(function () {
        // EntertainmentTask::created fires a log watcher that needs an authenticated
        // employee; these rows are pure query fixtures, so create them without events
        // and supply the uid the ModelObserver would normally generate.
        $linkTasks = function (string $songName, int $times) {
            $songId = ProjectSongItem::where('song_name', $songName)->value('id');

            for ($i = 0; $i < $times; $i++) {
                $task = EntertainmentTask::withoutEvents(fn () => EntertainmentTask::create([
                    'uid' => Str::uuid()->toString(),
                    'project_id' => $this->project->id,
                    'name' => "Task {$i} for {$songName}",
                ]));

                EntertainmentTaskSongItem::create([
                    'entertainment_task_id' => $task->id,
                    'song_item_id' => $songId,
                ]);
            }
        };

        // 'A' (in the Single group) is linked to one task; 'B' (in Triple) to two.
        $linkTasks('A', 1);
        $linkTasks('B', 2);
    });

    it('resolves a dotted path without any extra repository handling', function () {
        expect(($this->idsMatching)(['has' => ['items.entertainmentTaskSongItems']]))
            ->toBe([$this->single->id, $this->triple->id]);
    });

    it('applies the count to the innermost segment, not the outer one', function () {
        // 'B' carries 2 task links, 'A' only 1 - so only the Triple group survives.
        // Note this is NOT "groups with >= 2 songs": the Triple group has 3 songs but
        // would still be excluded if none of them had 2 task links.
        expect(($this->idsMatching)(['has' => ['items.entertainmentTaskSongItems' => 2]]))
            ->toBe([$this->triple->id]);
    });

    it('supports a dotted path in doesntHave too', function () {
        expect(($this->idsMatching)(['doesntHave' => ['items.entertainmentTaskSongItems']]))
            ->toBe([$this->empty->id]);
    });

    it('mixes a dotted path with a flat one in the same array', function () {
        expect(($this->idsMatching)([
            'has' => ['items' => 3, 'items.entertainmentTaskSongItems' => 2],
        ]))->toBe([$this->triple->id]);
    });
});
