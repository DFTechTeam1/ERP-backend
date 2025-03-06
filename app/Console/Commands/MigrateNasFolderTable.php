<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Production\Models\NasFolderCreation;
use Modules\Production\Models\NasFolderCreationBackup;

class MigrateNasFolderTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-nas-folder-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $nasFolder = NasFolderCreation::where('status', 1)
            ->orWhere('status', 2)
            ->get();

        NasFolderCreationBackup::truncate();

        foreach ($nasFolder as $key => $folder) {
            $paths = json_decode($folder->folder_path, true);

            [,$year,$monthName,$fullProjectName] = explode('/', $paths[0]);
            [,$month] = explode('_', $monthName);
            [$prefix,$projectName] = explode("_{$month}_", $fullProjectName);

            $payload = [
                'shared_folder' => 'Queue_Job_11',
                'year' => $year,
                'month_name' => $monthName,
                'project_name' => $projectName,
                'prefix_project_name' => $prefix,
                'child_folders' => [
                    'ASSET_3D',
                    'ASSET_FOOTAGE',
                    'ASSET_SEMENTARA',
                    'AUDIO',
                    'BRIEF',
                    'FINAL_RENDER',
                    'PREVIEW',
                    'RAW',
                    'SKETSA',
                    'TC'
                ],
                'project_id' => $folder->project_id,
            ];

            NasFolderCreationBackup::create($payload);
        }
    }
}
