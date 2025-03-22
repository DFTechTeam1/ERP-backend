<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class InitateAvatarColor extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:initate-avatar-color';

    /**
     * The console command description.
     */
    protected $description = "Register color based on employee's email";

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
        $employees = Employee::selectRaw('id,email')
            ->where('deleted_at', null)
            ->get();

        foreach ($employees as $employee) {
            $color = generateRandomColor(email: $employee->email);
            Employee::where('id', $employee->id)
                ->update(['avatar_color' => $color]);
        }

        $this->info("All employees has been updated");
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
