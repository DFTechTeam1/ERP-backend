<?php

namespace App\Console\Commands;

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TransferTeamStatus;
use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

class GenerateProjectMeta extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-project-meta';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate project meta for past projects';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projects = Project::selectRaw('id')
            ->with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,boss_id',
                'teamTransfer' => function ($query) {
                    $query->selectRaw('id,project_id,employee_id,alternative_employee_id')
                        ->whereIn('status', [
                            TransferTeamStatus::Approved->value,
                            TransferTeamStatus::Completed->value,
                            TransferTeamStatus::ApprovedWithAlternative->value,
                        ]);
                },
            ])
            ->where('status', ProjectStatus::Completed->value)
            ->get();

        $format = [];
        foreach ($projects as $key => $project) {
            $format[$key] = [
                'project_id' => $project->id,
                'teams_meta' => [],
            ];

            foreach ($project->personInCharges as $keyPic => $pic) {
                $format[$key]['teams_meta'][$keyPic] = [
                    'pic_id' => $pic->pic_id,
                    'teams' => [],
                    'team_transfer' => [],
                ];

                // generate teams member
                $members = Employee::selectRaw('id')
                    ->where('boss_id', $pic->pic_id)
                    ->get();

                $format[$key]['teams_meta'][$keyPic]['teams'] = collect($members)->pluck('id')->toArray();

                // generate transfer team
                $transferDirect = collect($project->teamTransfer)->where('requested_by', '=', $pic->pic_id)
                    ->where('alternative_employee_id', '=', null)
                    ->pluck('employee_id')->toArray();

                $transferAlternative = collect($project->teamTransfer)->where('requested_by', '=', $pic->pic_id)
                    ->pluck('employee_id')->toArray();

                $transferDirect = collect($transferDirect)->merge($transferAlternative);

                $format[$key]['teams_meta'][$keyPic]['team_transfer'] = $transferDirect;
            }
        }
        $format = array_values($format);

        foreach ($format as $payload) {
            \Modules\Production\Models\ProjectMeta::create($payload);
        }

        $this->info('All meta has been generated');
    }
}
