<?php

namespace App\Console\Commands;

use App\Enums\Production\ProjectStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Modules\Production\Models\Project;
use Modules\Production\Services\ProjectService;
use RuntimeException;
use Throwable;

class ProjectDuplicateCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:project-duplicate-cleanup
                            {--force : Delete the redundant projects. Without this flag the command only reports what it would do.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find projects sharing a name and project date, then delete the redundant copies';

    /**
     * Relations counted to break a tie between duplicates of equal status. The
     * copy carrying the most downstream work is the one worth keeping.
     *
     * @var array<int, string>
     */
    protected array $downstreamRelations = ['tasks', 'personInCharges', 'boards', 'references'];

    /**
     * Survivor preference, most advanced first. A project that already reached
     * production outranks one still being drafted; a cancelled project is the
     * least valuable copy and is only kept when it is the sole candidate.
     *
     * @var array<int, ProjectStatus>
     */
    protected array $statusPriority = [
        ProjectStatus::Completed,
        ProjectStatus::PartialComplete,
        ProjectStatus::OnGoing,
        ProjectStatus::ReadyToGo,
        ProjectStatus::ApprovedByClient,
        ProjectStatus::WaitingApprovalClient,
        ProjectStatus::Revise,
        ProjectStatus::Draft,
        ProjectStatus::Canceled,
    ];

    /**
     * A project is a duplicate when it shares the same name AND the same
     * project_date with another project, regardless of its status.
     *
     * @return SupportCollection<string, Collection<int, Project>>
     */
    protected function getDuplicateProjectGroups(): SupportCollection
    {
        $duplicateKeys = Project::query()
            ->select(['name', 'project_date'])
            ->groupBy('name', 'project_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateKeys->isEmpty()) {
            return collect([]);
        }

        return Project::query()
            ->select(['id', 'uid', 'name', 'status', 'project_date', 'project_deal_id'])
            ->withCount($this->downstreamRelations)
            ->whereIn('name', $duplicateKeys->pluck('name')->unique()->all())
            ->whereIn('project_date', $duplicateKeys->pluck('project_date')->unique()->all())
            ->with(['personInCharges:id,project_id,pic_id', 'personInCharges.employee:id,nickname'])
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy(fn (Project $project) => $this->buildDuplicateKey($project->name, $project->project_date))
            ->filter(fn (Collection $group) => $group->count() > 1);
    }

    /**
     * Lower-cased and trimmed so PHP grouping matches the case-insensitive
     * collation MySQL used to build the groups in the first place.
     */
    protected function buildDuplicateKey(string $name, string $projectDate): string
    {
        return mb_strtolower(trim($name)).'|'.$projectDate;
    }

    /**
     * Split a duplicate group into the copy to keep and the copies to delete.
     *
     * Rows holding *different* project deals are not copies of one another —
     * they are two separately quoted deals that happen to share a name and a
     * date. Those are reported for a human to judge and never deleted.
     *
     * @param  Collection<int, Project>  $group
     * @return array{verdict: string, keep: ?Project, delete: Collection<int, Project>}
     */
    protected function classifyDuplicateGroup(Collection $group): array
    {
        $dealIds = $group->pluck('project_deal_id')->filter()->unique();

        if ($dealIds->count() > 1) {
            return ['verdict' => 'review', 'keep' => null, 'delete' => new Collection];
        }

        $keep = $this->chooseSurvivor($group);

        return [
            'verdict' => 'duplicate',
            'keep' => $keep,
            'delete' => $group->reject(fn (Project $project) => $project->id === $keep->id)->values(),
        ];
    }

    /**
     * @param  Collection<int, Project>  $group
     */
    protected function chooseSurvivor(Collection $group): Project
    {
        return $group->sortBy([
            fn (Project $a, Project $b) => $this->statusRank($a->status) <=> $this->statusRank($b->status),
            fn (Project $a, Project $b) => $this->countDownstreamRecords($b) <=> $this->countDownstreamRecords($a),
            fn (Project $a, Project $b) => $a->id <=> $b->id,
        ])->first();
    }

    /**
     * Unknown or null statuses rank last so they are never preferred over a
     * project whose status we can actually reason about.
     */
    protected function statusRank(?int $status): int
    {
        foreach ($this->statusPriority as $rank => $case) {
            if ($case->value === $status) {
                return $rank;
            }
        }

        return count($this->statusPriority);
    }

    protected function countDownstreamRecords(Project $project): int
    {
        return collect($this->downstreamRelations)
            ->sum(fn (string $relation) => (int) $project->getAttribute(str($relation)->snake()->value().'_count'));
    }

    /**
     * ProjectService::bulkDelete() cascades into deleteProjectDeal(), which
     * deletes the project_deals row and its NAS folder whenever the project
     * carries a project_deal_id. Every duplicate here shares its deal with the
     * copy we are keeping, so the link is cut first and the deal survives.
     *
     * Both steps share one transaction: bulkDelete() swallows its own failures
     * and returns an error response, so it is rethrown here to make sure a
     * failed delete cannot leave the deal links wiped behind it.
     *
     * @param  Collection<int, Project>  $projects
     */
    protected function deleteProjects(Collection $projects): void
    {
        DB::transaction(function () use ($projects) {
            Project::query()
                ->whereIn('id', $projects->pluck('id')->all())
                ->update(['project_deal_id' => null]);

            $response = app(ProjectService::class)->bulkDelete($projects->pluck('uid')->all());

            if ($response['error']) {
                throw new RuntimeException($response['message']);
            }
        });
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groups = $this->getDuplicateProjectGroups();

        if ($groups->isEmpty()) {
            $this->info('No duplicate projects found.');

            return self::SUCCESS;
        }

        $deletable = new Collection;
        $needsReview = collect([]);
        $rows = [];

        foreach ($groups as $group) {
            $result = $this->classifyDuplicateGroup($group);

            if ($result['verdict'] === 'review') {
                $needsReview->push($group);
            } else {
                $deletable = $deletable->merge($result['delete']);
            }

            foreach ($group as $project) {
                $rows[] = [
                    $project->id,
                    $project->name,
                    $project->project_date,
                    $project->status === null ? '-' : (ProjectStatus::tryFrom($project->status)?->name ?? $project->status),
                    $project->personInCharges->isEmpty() ? '-' : $project->personInCharges->pluck('employee.nickname')->join(','),
                    $project->project_deal_id ?? '-',
                    $this->countDownstreamRecords($project),
                    $this->describeVerdict($result, $project),
                ];
            }
        }

        $rows = collect($rows)->map(function ($mapping) {
            if ($mapping[7] === 'DELETE') {
                $mapping[0] = '<fg=red>'.$mapping[0].'</>';
                $mapping[1] = '<fg=red>'.$mapping[1].'</>';
                $mapping[2] = '<fg=red>'.$mapping[2].'</>';
                $mapping[3] = '<fg=red>'.$mapping[3].'</>';
                $mapping[4] = '<fg=red>'.$mapping[4].'</>';
                $mapping[5] = '<fg=red>'.$mapping[5].'</>';
                $mapping[6] = '<fg=red>'.$mapping[6].'</>';
                $mapping[7] = '<fg=red>'.$mapping[7].'</>';
            }

            return $mapping;
        })->toArray();

        $this->table(
            ['ID', 'Name', 'Date', 'Status', 'PIC', 'Deal', 'Records', 'Verdict'],
            $rows
        );

        $this->line(sprintf(
            '%d duplicate group(s): %d project(s) to delete, %d group(s) need manual review.',
            $groups->count(),
            $deletable->count(),
            $needsReview->count()
        ));

        if ($deletable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->comment('Dry run. Re-run with --force to delete the projects listed as DELETE.');

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            'About to permanently delete %d project(s) from database "%s" on host "%s".',
            $deletable->count(),
            config('database.connections.'.config('database.default').'.database'),
            config('database.connections.'.config('database.default').'.host')
        ));

        if (! $this->confirm('Delete these projects permanently?', false)) {
            $this->comment('Aborted. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $this->deleteProjects($deletable);
        } catch (Throwable $throwable) {
            $this->error('Delete failed, nothing was changed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Deleted %d duplicate project(s).', $deletable->count()));

        return self::SUCCESS;
    }

    /**
     * @param  array{verdict: string, keep: ?Project, delete: Collection<int, Project>}  $result
     */
    protected function describeVerdict(array $result, Project $project): string
    {
        if ($result['verdict'] === 'review') {
            return 'REVIEW (separate deals)';
        }

        return $result['keep']->id === $project->id ? 'KEEP' : 'DELETE';
    }
}
