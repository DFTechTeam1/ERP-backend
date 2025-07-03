<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TruncateEmployeeTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:truncate-employee';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate employee data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Schema::disableForeignKeyConstraints();
        \Modules\Hrd\Models\Employee::truncate();

        \Modules\Company\Models\Position::truncate();

        Schema::enableForeignKeyConstraints();
    }
}
