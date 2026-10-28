<?php

namespace Modules\Production\Console;

use App\Enums\Production\ProjectStatus;
use Illuminate\Console\Command;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;

class BackfillProjectLeadPic extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:backfill-project-lead-pic {--dry-run : List the projects that would be updated without writing anything}';

    /**
     * The console command description.
     */
    protected $description = 'One-time backfill: flag the first PIC as is_lead on ongoing/draft projects that have a PIC but no lead yet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $leadPicIds = [];

        Project::query()
            ->select(['id', 'name', 'status'])
            ->whereIn('status', [ProjectStatus::OnGoing->value, ProjectStatus::Draft->value])
            ->whereHas('personInCharges')
            ->whereDoesntHave('personInCharges', function ($query): void {
                $query->where('is_lead', true);
            })
            ->with(['personInCharges' => function ($query): void {
                $query->select(['id', 'project_id', 'pic_id'])->oldest('id');
            }])
            ->chunkById(200, function ($projects) use (&$leadPicIds, $dryRun): void {
                foreach ($projects as $project) {
                    $firstPic = $project->personInCharges->first();

                    if (! $firstPic) {
                        continue;
                    }

                    $leadPicIds[] = $firstPic->id;

                    if ($dryRun) {
                        $this->line("  [{$project->status}] Project #{$project->id} \"{$project->name}\" -> PIC #{$firstPic->pic_id} (pivot #{$firstPic->id})");
                    }
                }
            });

        $count = count($leadPicIds);

        if ($count === 0) {
            $this->info('No ongoing/draft projects need a lead PIC backfill. Nothing to do.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} project(s) would have their first PIC flagged as lead.");

            return self::SUCCESS;
        }

        ProjectPersonInCharge::query()
            ->whereIn('id', $leadPicIds)
            ->update(['is_lead' => true]);

        $this->info("Flagged {$count} PIC(s) as lead across ongoing/draft projects.");

        return self::SUCCESS;
    }
}
