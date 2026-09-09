<?php

use App\Actions\Project\DetailProject;
use App\Enums\Production\ProjectStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * DetailProject action + ProjectService::show() (which delegates to it).
 *
 * DetailProject owns the project-detail cache (getCache/storeCache on "detailProject{id}")
 * and enforces entertainment-role authorization. FormatTaskPermission runs on EVERY call
 * (cache hit included), so the setup below registers the permissions/settings it reads.
 *
 * The cache tests pre-seed the cache with a sentinel name ("FROM_CACHE") while the DB row has
 * a different name ("FROM_DB"), so a returned "FROM_CACHE" proves the cached payload was used
 * and the heavy rebuild was skipped.
 */
const DP_PERMISSIONS = [
    'list_member', 'list_entertainment_member', 'add_team_member', 'add_references',
    'list_request_song', 'create_request_song', 'distribute_request_song', 'add_showreels',
    'list_task', 'create_pool_task', 'delete_task', 'detail_cost_estimation',
    'complete_project', 'edit_task_description', 'add_task_description',
    'delete_task_description', 'move_board',
];

function dpService(): ProjectService
{
    return app(ProjectService::class);
}

function dpRunAction(string $uid)
{
    return DetailProject::run($uid, app(ProjectRepository::class), app(EntertainmentTaskSongRepository::class));
}

/**
 * @param  array<int, string>  $roles
 * @return array{0: User, 1: Employee}
 */
function dpActingAs(array $roles = []): array
{
    // GetProjectTeams reads $user->roles[0]->id, so the actor always needs at least one role.
    $roles = empty($roles) ? [BaseRole::Production->value] : $roles;

    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();

    foreach ($roles as $role) {
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']));
    }

    actingAs($user);

    return [$user, $employee];
}

function dpSeedDetailCache(Project $project, array $overrides = []): array
{
    $output = array_merge([
        'id' => $project->id,
        'uid' => $project->uid,
        'name' => 'FROM_CACHE',
        'boards' => [],
        'feedbacks' => [],
        'project_date' => $project->project_date,
        'status_raw' => ProjectStatus::OnGoing->value,
        'is_my_feedback_exists' => false,
    ], $overrides);

    storeCache('detailProject'.$project->id, $output);

    return $output;
}

function dpMakeEntertainmentTask(Employee $employee): void
{
    $songProject = Project::factory()->create();
    $song = ProjectSongList::create([
        'uid' => (string) Str::uuid(),
        'project_id' => $songProject->id,
        'name' => 'Song',
        'created_by' => 1,
    ]);
    EntertainmentTaskSong::create([
        'project_song_list_id' => $song->id,
        'employee_id' => $employee->id,
        'project_id' => $songProject->id,
        'status' => 1,
    ]);
}

beforeEach(function () {
    foreach (DP_PERMISSIONS as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }

    // lead_3d_modeller is read unguarded (getIdFromUid on the raw value), so it must be present.
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => Employee::factory()->create()->uid],
        ['key' => 'days_to_raise_deadline_alert', 'value' => '2'],
    ]);
});

describe('DetailProject action - cache', function () {
    it('returns the cached payload (skipping the rebuild) when the cache exists', function () {
        dpActingAs();
        $project = Project::factory()->create(['name' => 'FROM_DB']);
        dpSeedDetailCache($project);

        $output = dpRunAction($project->uid);

        expect($output['name'])->toBe('FROM_CACHE')          // cache used, DB row ignored
            ->and($output)->toHaveKey('permission_list')     // FormatTaskPermission applied
            ->and($output)->toHaveKey('teams')
            ->and($output)->toHaveKey('is_project_pic');
    });

    it('builds the detail from the database and stores it in the cache on a cold cache', function () {
        dpActingAs();
        $project = Project::factory()->withBoards()->create(['name' => 'COLD BUILD']);

        expect(getCache('detailProject'.$project->id))->toBeNull(); // cold

        $output = dpRunAction($project->uid);

        // built from the DB (real name, not a sentinel) with the full detail shape
        expect($output['name'])->toBe('COLD BUILD')
            ->and($output['uid'])->toBe($project->uid)
            ->and($output)->toHaveKey('boards')
            ->and($output)->toHaveKey('progress')
            ->and($output)->toHaveKey('permission_list')
            ->and($output)->toHaveKey('project_maximal_point');

        // the build populated the cache
        expect(getCache('detailProject'.$project->id))->not->toBeNull();

        // a later DB rename is NOT reflected - the stored cache is reused
        Project::where('id', $project->id)->update(['name' => 'RENAMED IN DB']);

        expect(dpRunAction($project->uid)['name'])->toBe('COLD BUILD');
    });
});

describe('DetailProject action - entertainment authorization', function () {
    it('denies an entertainment user with no task and no VJ assignment', function () {
        [, $employee] = dpActingAs([BaseRole::Entertainment->value]);
        $project = Project::factory()->create();

        expect(fn () => dpRunAction($project->uid))->toThrow(AuthorizationException::class);
    });

    it('allows an entertainment user who has an entertainment task', function () {
        [, $employee] = dpActingAs([BaseRole::Entertainment->value]);
        $project = Project::factory()->create();
        dpMakeEntertainmentTask($employee);
        dpSeedDetailCache($project);

        $output = dpRunAction($project->uid);

        expect($output['name'])->toBe('FROM_CACHE');
    });

    it('allows an entertainment user who is a VJ on the project', function () {
        [$user, $employee] = dpActingAs([BaseRole::Entertainment->value]);
        $project = Project::factory()->create();
        $project->vjs()->create(['employee_id' => $employee->id, 'created_by' => $user->id]);
        dpSeedDetailCache($project);

        $output = dpRunAction($project->uid);

        expect($output['name'])->toBe('FROM_CACHE');
    });

    it('does not restrict a non-entertainment user', function () {
        dpActingAs([BaseRole::Production->value]);
        $project = Project::factory()->create();
        dpSeedDetailCache($project);

        $output = dpRunAction($project->uid);

        expect($output['name'])->toBe('FROM_CACHE');
    });
});

describe('ProjectService::show', function () {
    it('returns the cached detail wrapped in the response envelope', function () {
        dpActingAs();
        $project = Project::factory()->create(['name' => 'FROM_DB']);
        dpSeedDetailCache($project);

        $response = dpService()->show($project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['data']['name'])->toBe('FROM_CACHE');
    });

    it('maps the entertainment authorization failure to a 403 error response', function () {
        dpActingAs([BaseRole::Entertainment->value]);
        $project = Project::factory()->create();

        $response = dpService()->show($project->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(403);
    });
});

describe('GET /api/production/project/{uid} (e2e)', function () {
    it('returns the cached project detail', function () {
        dpActingAs();
        $project = Project::factory()->create(['name' => 'FROM_DB']);
        dpSeedDetailCache($project);

        $this->getJson("/api/production/project/{$project->uid}")
            ->assertStatus(201)
            ->assertJsonPath('data.data.name', 'FROM_CACHE');
    });

    it('returns a 403 for an unauthorized entertainment user', function () {
        dpActingAs([BaseRole::Entertainment->value]);
        $project = Project::factory()->create();

        $this->getJson("/api/production/project/{$project->uid}")
            ->assertStatus(403);
    });
});
