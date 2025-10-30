<?php

namespace Modules\Production\Console;

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Console\Command;
use Modules\Company\Jobs\SlackNotificationJob;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ResyncNasFolderCreation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:resync-nas-folder-creation';

    /**
     * The console command description.
     */
    protected $description = 'Resync project deals to NAS folder creation table';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // check project_deals who already have 'published_at' but not in nas_folder_creations
        $projectDeals = \Modules\Production\Models\ProjectDeal::selectRaw('id,name,published_at,status')->whereNotNull('published_at')
            ->where('status', ProjectDealStatus::Final)
            ->with([
                'project' => function ($query) {
                    $query->selectRaw('id,project_deal_id,project_date')
                    ->whereDoesntHave('nasFolderCreation');
                }
            ])
            ->get();

        // call nas folder creation service to create nas folder
        $nasFolderCreationService = app(\App\Services\NasFolderCreationService::class);

        $totalProjectThatNeedNasFolder = $projectDeals->count();
        // notify slack
        SlackNotificationJob::dispatch(
            previewMessage: "Resync NAS Folder Creation - {$totalProjectThatNeedNasFolder} Projects Need NAS Folder",
            message: "Resync NAS Folder Creation started. Total projects that need NAS folder: {$totalProjectThatNeedNasFolder}",
            blockHeader: "Resync NAS Folder Creation"
        );

        foreach ($projectDeals as $projectDeal) {
            if ($projectDeal->project) {
                $nasFolderCreationService->sendRequest(
                    payload: [
                        "project_id" => $projectDeal->project->id,
                        "project_name" => $projectDeal->project->name,
                        "project_date" => $projectDeal->project->project_date,
                    ],
                    type: 'create'
                );
                $this->info("NAS folder created for Project Deal ID: {$projectDeal->id}, Name: {$projectDeal->name}");
            }
        }
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
