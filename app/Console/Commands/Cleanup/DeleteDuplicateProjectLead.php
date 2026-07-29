<?php

namespace App\Console\Commands\Cleanup;

use App\Enums\Production\ProjectLeadStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Production\Models\ProjectLead;
use Modules\Production\Models\ProjectLeadFollowUp;
use Modules\Production\Models\ProjectLeadQueue;
use Modules\Production\Repository\ProjectLeadRepository;
use Throwable;

class DeleteDuplicateProjectLead extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-duplicate-project-lead
                            {--force : Delete the redundant leads. Without this flag the command only reports what it would do.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find project leads sharing a name and project date plus leads named as test data, then delete the redundant copies';

    /**
     * Words that mark a lead as throwaway test data. Matched as whole words with
     * an optional trailing number, so "Test", "testing 2" and "Dummy Lead" are
     * caught while a real client named "Latest Project" or "Testimoni" is not.
     *
     * @var array<int, string>
     */
    protected array $junkNameWords = ['test', 'testing', 'dummy'];

    /**
     * Columns compared to decide which copy carries the most complete data.
     *
     * name and project_date are identical inside a group, ids and audit stamps
     * say nothing about completeness, and the cancel_* columns are only ever
     * filled on a cancelled lead - counting them would make a cancelled copy
     * look richer than the active lead it duplicates.
     *
     * @var array<int, string>
     */
    protected array $comparedColumns = [
        'customer_phone',
        'event_type',
        'venue',
        'city_id',
        'pic_id',
        'collaboration',
        'note',
        'total_led',
        'led_detail',
        'project_class_id',
        'cell_options',
    ];

    /**
     * Survivor preference, most valuable first. A cancelled lead is only kept
     * when the whole group is cancelled.
     *
     * @var array<int, ProjectLeadStatus>
     */
    protected array $statusPriority = [
        ProjectLeadStatus::ACTIVE,
        ProjectLeadStatus::CANCELLED,
    ];

    /**
     * A lead is a duplicate when it shares the same name AND the same
     * project_date with another lead, regardless of its status.
     *
     * @return SupportCollection<string, Collection<int, ProjectLead>>
     */
    protected function getDuplicateLeadGroups(): SupportCollection
    {
        $duplicateKeys = ProjectLead::query()
            ->select(['name', 'project_date'])
            ->groupBy('name', 'project_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateKeys->isEmpty()) {
            return collect([]);
        }

        return ProjectLead::query()
            ->select(array_merge(['id', 'uid', 'name', 'project_date', 'status', 'project_deal_id'], $this->comparedColumns))
            ->withCount('followUps')
            ->whereIn('name', $duplicateKeys->pluck('name')->unique()->all())
            ->whereIn('project_date', $duplicateKeys->pluck('project_date')->unique()->all())
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy(fn (ProjectLead $lead) => $this->buildDuplicateKey($lead->name, $lead->project_date))
            ->filter(fn (Collection $group) => $group->count() > 1);
    }

    /**
     * Leads whose name marks them as test data. They are junk on their own, no
     * duplicate needed - but a lead already linked to a project deal is never
     * deleted here either, since real work may be hanging off it.
     *
     * The LIKE filter is only a coarse net to keep the scan small; hasJunkName()
     * makes the actual call.
     *
     * @return Collection<int, ProjectLead>
     */
    protected function getJunkNamedLeads(): Collection
    {
        return ProjectLead::query()
            ->select(array_merge(['id', 'uid', 'name', 'project_date', 'status', 'project_deal_id'], $this->comparedColumns))
            ->withCount('followUps')
            ->where(function ($query) {
                foreach ($this->junkNameWords as $word) {
                    $query->orWhere('name', 'like', '%'.$word.'%');
                }
            })
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn (ProjectLead $lead) => $this->hasJunkName($lead->name))
            ->values();
    }

    protected function hasJunkName(string $name): bool
    {
        $words = collect($this->junkNameWords)
            ->map(fn (string $word) => preg_quote($word, '/'))
            ->join('|');

        return preg_match('/\b(?:'.$words.')\s*\d*\b/i', $name) === 1;
    }

    /**
     * Junk leads are reported and deleted on their own, so they are pulled out
     * of the duplicate groups first. A group left with a single row is no longer
     * a duplicate group at all.
     *
     * @param  SupportCollection<string, Collection<int, ProjectLead>>  $groups
     * @param  array<int, int>  $junkIds
     * @return SupportCollection<string, Collection<int, ProjectLead>>
     */
    protected function excludeJunkLeads(SupportCollection $groups, array $junkIds): SupportCollection
    {
        if (empty($junkIds)) {
            return $groups;
        }

        return $groups
            ->map(fn (Collection $group) => $group->reject(fn (ProjectLead $lead) => in_array($lead->id, $junkIds, true))->values())
            ->filter(fn (Collection $group) => $group->count() > 1);
    }

    /**
     * Lower-cased and trimmed so PHP grouping matches the case-insensitive
     * collation the database used to build the groups in the first place.
     */
    protected function buildDuplicateKey(string $name, string $projectDate): string
    {
        return mb_strtolower(trim($name)).'|'.$projectDate;
    }

    /**
     * Split a duplicate group into the copy to keep and the copies to delete.
     *
     * Rows holding *different* project deals are not copies of one another -
     * they are two separately quoted deals that happen to share a name and a
     * date. Those are reported for a human to judge and never deleted.
     *
     * @param  Collection<int, ProjectLead>  $group
     * @return array{verdict: string, keep: ?ProjectLead, delete: Collection<int, ProjectLead>}
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
            'delete' => $group->reject(fn (ProjectLead $lead) => $lead->id === $keep->id)->values(),
        ];
    }

    /**
     * A lead already linked to a project deal always wins - downstream work is
     * hanging off it. The rest are tie-breaks: the richer row, then the row
     * carrying follow up history, and finally the oldest id, which means the
     * newest copy is the one deleted when the rows are identical.
     *
     * @param  Collection<int, ProjectLead>  $group
     */
    protected function chooseSurvivor(Collection $group): ProjectLead
    {
        return $group->sortBy([
            fn (ProjectLead $a, ProjectLead $b) => ($b->project_deal_id ? 1 : 0) <=> ($a->project_deal_id ? 1 : 0),
            fn (ProjectLead $a, ProjectLead $b) => $this->statusRank($a->status) <=> $this->statusRank($b->status),
            fn (ProjectLead $a, ProjectLead $b) => $this->countFilledColumns($b) <=> $this->countFilledColumns($a),
            fn (ProjectLead $a, ProjectLead $b) => (int) $b->follow_ups_count <=> (int) $a->follow_ups_count,
            fn (ProjectLead $a, ProjectLead $b) => $a->id <=> $b->id,
        ])->first();
    }

    /**
     * Unknown or null statuses rank last so they are never preferred over a
     * lead whose status we can actually reason about.
     */
    protected function statusRank(?ProjectLeadStatus $status): int
    {
        foreach ($this->statusPriority as $rank => $case) {
            if ($case === $status) {
                return $rank;
            }
        }

        return count($this->statusPriority);
    }

    /**
     * Raw attributes are read on purpose: the casts turn pic_id into an array
     * and status into an enum, and an empty JSON payload still has to count as
     * an empty column.
     */
    protected function countFilledColumns(ProjectLead $lead): int
    {
        $attributes = $lead->getAttributes();

        return collect($this->comparedColumns)
            ->filter(function (string $column) use ($attributes) {
                $value = $attributes[$column] ?? null;

                if ($value === null) {
                    return false;
                }

                return ! in_array(trim((string) $value), ['', '[]', '{}', 'null'], true);
            })
            ->count();
    }

    /**
     * Follow ups are removed explicitly instead of leaning on the foreign key
     * cascade, so the cleanup behaves the same on every connection. Both steps
     * share one transaction: a failing delete must not leave a surviving lead
     * stripped of its follow up history.
     *
     * project_lead_queue is owned by the FastAPI service's migrations, not this
     * application, so it is only cleaned when the connection actually carries
     * it - a Laravel-only database (a fresh one, or the test database) never
     * does. Its foreign key is ON DELETE NO ACTION, so where the table does
     * exist the queue rows must go first or the lead delete is refused.
     *
     * @param  Collection<int, ProjectLead>  $leads
     */
    protected function deleteLeads(Collection $leads): void
    {
        DB::transaction(function () use ($leads) {
            $ids = $leads->pluck('id')->all();

            ProjectLeadFollowUp::query()
                ->whereIn('project_lead_id', $ids)
                ->delete();

            if (Schema::hasTable((new ProjectLeadQueue)->getTable())) {
                ProjectLeadQueue::query()
                    ->whereIn('lead_id', $ids)
                    ->delete();
            }

            app(ProjectLeadRepository::class)->bulkDelete($ids, 'id');
        });
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $junkLeads = $this->getJunkNamedLeads();
        $junkDeletable = $junkLeads->reject(fn (ProjectLead $lead) => $lead->project_deal_id)->values();

        $groups = $this->excludeJunkLeads($this->getDuplicateLeadGroups(), $junkDeletable->pluck('id')->all());

        if ($groups->isEmpty() && $junkLeads->isEmpty()) {
            $this->info('No duplicate or test named project leads found.');

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

            foreach ($group as $lead) {
                $verdict = $this->describeVerdict($result, $lead);

                $rows[] = $this->highlightDeletion([
                    $lead->id,
                    $lead->name,
                    $lead->project_date,
                    $lead->status?->value ?? '-',
                    $lead->project_deal_id ?? '-',
                    $this->countFilledColumns($lead).'/'.count($this->comparedColumns),
                    (int) $lead->follow_ups_count,
                    $verdict,
                ], $verdict);
            }
        }

        if ($rows) {
            $this->line('Duplicate leads (same name and project date):');
            $this->table(
                ['ID', 'Name', 'Date', 'Status', 'Deal', 'Filled', 'Follow Ups', 'Verdict'],
                $rows
            );
        }

        if ($groups->isNotEmpty()) {
            $this->line(sprintf(
                '%d duplicate group(s): %d lead(s) to delete, %d group(s) need manual review.',
                $groups->count(),
                $deletable->count(),
                $needsReview->count()
            ));
        }

        if ($junkLeads->isNotEmpty()) {
            $this->reportJunkLeads($junkLeads);

            $deletable = $deletable->merge($junkDeletable);
        }

        if ($deletable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->comment('Dry run. Re-run with --force to delete the leads listed as DELETE.');

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            'About to permanently delete %d project lead(s) from database "%s" on host "%s".',
            $deletable->count(),
            config('database.connections.'.config('database.default').'.database'),
            config('database.connections.'.config('database.default').'.host')
        ));

        if (! $this->confirm('Delete these project leads permanently?', false)) {
            $this->comment('Aborted. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $this->deleteLeads($deletable);
        } catch (Throwable $throwable) {
            $this->error('Delete failed, nothing was changed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Deleted %d project lead(s).', $deletable->count()));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ProjectLead>  $junkLeads
     */
    protected function reportJunkLeads(Collection $junkLeads): void
    {
        $rows = $junkLeads->map(function (ProjectLead $lead) {
            $verdict = $lead->project_deal_id ? 'REVIEW (linked to a deal)' : 'DELETE';

            return $this->highlightDeletion([
                $lead->id,
                $lead->name,
                $lead->project_date,
                $lead->status?->value ?? '-',
                $lead->project_deal_id ?? '-',
                (int) $lead->follow_ups_count,
                $verdict,
            ], $verdict);
        })->all();

        $this->line('Test named leads:');
        $this->table(
            ['ID', 'Name', 'Date', 'Status', 'Deal', 'Follow Ups', 'Verdict'],
            $rows
        );

        $linked = $junkLeads->filter(fn (ProjectLead $lead) => $lead->project_deal_id)->count();

        $this->line(sprintf(
            '%d test named lead(s): %d to delete, %d kept for manual review.',
            $junkLeads->count(),
            $junkLeads->count() - $linked,
            $linked
        ));
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    protected function highlightDeletion(array $row, string $verdict): array
    {
        if ($verdict !== 'DELETE') {
            return $row;
        }

        return array_map(fn ($value) => '<fg=red>'.$value.'</>', $row);
    }

    /**
     * @param  array{verdict: string, keep: ?ProjectLead, delete: Collection<int, ProjectLead>}  $result
     */
    protected function describeVerdict(array $result, ProjectLead $lead): string
    {
        if ($result['verdict'] === 'review') {
            return 'REVIEW (separate deals)';
        }

        return $result['keep']->id === $lead->id ? 'KEEP' : 'DELETE';
    }
}
