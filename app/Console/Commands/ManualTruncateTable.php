<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManualTruncateTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:truncate-projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to manual truncate table and its relation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('projects')) {
            \Modules\Production\Models\ProjectTaskWorktime::truncate();
            \Modules\Production\Models\ProjectTaskReviseHistory::truncate();
            \Modules\Production\Models\ProjectTaskProofOfWork::truncate();
            \Modules\Production\Models\ProjectTaskPic::truncate();
            \Modules\Production\Models\ProjectTaskPicLog::truncate();
            \Modules\Production\Models\ProjectTaskLog::truncate();
            \Modules\Production\Models\ProjectTaskAttachment::truncate();
            \Modules\Production\Models\ProjectReference::truncate();
            \Modules\Production\Models\ProjectPersonInCharge::truncate();
            \Modules\Production\Models\ProjectMarketing::truncate();
            \Modules\Production\Models\ProjectEquipment::truncate();
            \Modules\Production\Models\ProjectTask::truncate();
            \Modules\Production\Models\ProjectBoard::truncate();

            \Modules\Production\Models\Project::truncate();
        }
    }
}
