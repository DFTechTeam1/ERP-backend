<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteAllTaskAndRelation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-all-task-relation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is just temporary command to reset the project task and points and logs and other relations. This is should be working on development and staging only';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (App::environment('production')) {
            $this->warn('This command only available form development');
        } else {
            $tables = [
                'project_task_attachments',
                'project_task_holds',
                'project_task_pic_logs',
                'project_task_pic_histories',
                'project_task_proof_of_works',
                'project_task_revise_histories',
                'project_task_worktimes',
                'project_task_pics',
                'employee_task_points',
                'project_tasks'
            ];

            // DB::beginTransaction();

            try {
                Schema::disableForeignKeyConstraints();
                foreach ($tables as $table) {
                    DB::table($table)
                        ->truncate();
                }
                Schema::enableForeignKeyConstraints();
                // DB::commit();

                Artisan::call('cache:clear');

                $this->info('Success delete all task relations');
            } catch (\Throwable $th) {
                // DB::rollBack();
                $this->error(errorMessage($th));
            }
        }
    }
}
