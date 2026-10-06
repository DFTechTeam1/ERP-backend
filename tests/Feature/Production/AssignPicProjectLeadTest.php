<?php

use App\Actions\Project\DetailCache;
use App\Actions\Project\FormatTaskPermission;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\RebalanceWorkloadAfterSubtitutePic;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectLead;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * PIC-of-project changes keep the linked project lead's pic_id in sync.
 *
 * Both assignPic() and subtitutePic() call changeProjectLeadPIC(), which re-derives pic_id from the
 * project's CURRENT project_person_in_charges rows (so the lead can't drift from the PIC table) and
 * writes it onto the linked lead - linked through the DEAL (projects.project_deal_id =
 * project_leads.project_deal_id, since project_leads has no project_id column) - then asks the Python
 * service to re-balance. A project with no deal, or a deal with no lead, is skipped.
 *
 * The heavy collaborators (DetailCache, FormatTaskPermission) are stubbed; the Python call is faked.
 */
beforeEach(function () {
    Queue::fake();

    // DetailCache is injected into ProjectService. assignPic() calls ->handle(); subtitutePic()
    // calls ->run() (a static AsAction method, so Mockery can't intercept it on the instance).
    // Bind a lightweight stub that overrides both and returns a shape the service can consume.
    $this->instance(DetailCache::class, new class extends DetailCache
    {
        public function handle(string $projectUid, array $necessaryUpdate = [], bool $forceUpdateAll = false)
        {
            return ['pic' => '', 'pic_ids' => []];
        }

        public static function run(mixed ...$arguments): mixed
        {
            return ['pic' => '', 'pic_ids' => []];
        }
    });

    $this->mock(FormatTaskPermission::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturnUsing(fn ($data, $projectId) => $data);
    });

    $this->service = app(ProjectService::class);
});

it('mirrors the assigned PICs onto the linked project lead and triggers the rebalance', function () {
    $deal = ProjectDeal::factory()->create();
    $project = Project::factory()->create(['project_deal_id' => $deal->id]);
    $lead = ProjectLead::factory()->create(['project_deal_id' => $deal->id, 'pic_id' => null]);

    $pmA = Employee::factory()->create();
    $pmB = Employee::factory()->create();

    $response = $this->service->assignPic($project->uid, [
        'pics' => [$pmA->uid, $pmB->uid],
    ]);

    expect($response['error'])->toBeFalse();

    // PICs are assigned to the project.
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmA->id]);
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id]);

    // The lead's pic_id mirrors the project's current PICs (read back via the model accessor;
    // order-independent since pic_id is a set).
    expect($lead->fresh()->pic_id)->toEqualCanonicalizing([$pmA->id, $pmB->id]);

    // The rebalance job is queued (it carries the lead uid to the Python service).
    Queue::assertPushed(RebalanceWorkloadAfterSubtitutePic::class);
});

it('skips the lead update and rebalance when the project has no deal', function () {
    $project = Project::factory()->create(['project_deal_id' => null]);
    $pm = Employee::factory()->create();

    $response = $this->service->assignPic($project->uid, ['pics' => [$pm->uid]]);

    expect($response['error'])->toBeFalse();
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pm->id]);

    Queue::assertNotPushed(RebalanceWorkloadAfterSubtitutePic::class);
});

it('skips the rebalance when the deal has no lead', function () {
    $deal = ProjectDeal::factory()->create();
    $project = Project::factory()->create(['project_deal_id' => $deal->id]);
    // No ProjectLead exists for this deal.
    $pm = Employee::factory()->create();

    $response = $this->service->assignPic($project->uid, ['pics' => [$pm->uid]]);

    expect($response['error'])->toBeFalse();
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pm->id]);

    Queue::assertNotPushed(RebalanceWorkloadAfterSubtitutePic::class);
});

it('re-syncs the lead pic_id with the project PICs after a substitute (remove + add)', function () {
    $deal = ProjectDeal::factory()->create();
    $project = Project::factory()->create(['project_deal_id' => $deal->id]);
    $lead = ProjectLead::factory()->create(['project_deal_id' => $deal->id]);

    $pmA = Employee::factory()->create();
    $pmB = Employee::factory()->create();
    $pmC = Employee::factory()->create();

    // Existing PICs on the project (and, implicitly, on the lead).
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => false]);
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);

    // Substitute: drop pmB, add pmC (pmC nominated as Lead).
    $response = $this->service->subtitutePic($project->uid, [
        'removed' => [$pmB->uid],
        'pics' => [$pmC->uid],
        'leader' => $pmC->uid,
    ]);

    expect($response['error'])->toBeFalse();

    // Project PICs are now pmA + pmC (pmB gone).
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmA->id]);
    assertDatabaseHas('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmC->id]);
    assertDatabaseMissing('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id]);

    // The lead's pic_id is re-derived from the project's current PICs - it matches, not drifts.
    expect($lead->fresh()->pic_id)->toEqualCanonicalizing([$pmA->id, $pmC->id]);
});

it('re-syncs the lead pic_id when a substitute only removes a PIC', function () {
    $deal = ProjectDeal::factory()->create();
    $project = Project::factory()->create(['project_deal_id' => $deal->id]);
    $lead = ProjectLead::factory()->create(['project_deal_id' => $deal->id]);

    $pmA = Employee::factory()->create();
    $pmB = Employee::factory()->create();
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmA->id, 'is_lead' => false]);
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pmB->id, 'is_lead' => false]);

    // Removal-only substitute (no additions) must still re-sync the lead. pmA stays as Lead.
    $response = $this->service->subtitutePic($project->uid, [
        'removed' => [$pmB->uid],
        'pics' => [],
        'leader' => $pmA->uid,
    ]);

    expect($response['error'])->toBeFalse();
    assertDatabaseMissing('project_person_in_charges', ['project_id' => $project->id, 'pic_id' => $pmB->id]);
    expect($lead->fresh()->pic_id)->toEqualCanonicalizing([$pmA->id]);
});
