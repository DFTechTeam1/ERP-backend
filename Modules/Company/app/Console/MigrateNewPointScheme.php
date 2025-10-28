<?php

namespace Modules\Company\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrateNewPointScheme extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:migrate-new-point-scheme';

    /**
     * The console command description.
     */
    protected $description = 'Fill new point scheme data into the database';

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
        $points = \Modules\Company\Models\ProjectClass::where('point_2_team', 0)
            ->where('point_3_team', 0)
            ->where('point_4_team', 0)
            ->where('point_5_team', 0)
            ->get();

        // B (Standard)
        // S (Spesial)
        // A (besar)
        // C (Budget)
        // D (Template)
        // B+ (Standard Plus)
        // A+ (Besar Plus)
        $progressBar = $this->output->createProgressBar(7);
        \Modules\Company\Models\ProjectClass::where('name', 'B (Standard)')
            ->orWhere('name', 'C (Budget)')
            ->orWhere('name', 'D (Template)')
            ->update([
                'base_point' => 25,
                'point_2_team' => 30,
                'point_3_team' => 36,
                'point_4_team' => 40,
                'point_5_team' => 45,
            ]);
        $progressBar->advance(3);

        \Modules\Company\Models\ProjectClass::where('name', 'S (Spesial)')
            ->update([
                'base_point' => 60,
                'point_2_team' => 72,
                'point_3_team' => 84,
                'point_4_team' => 96,
                'point_5_team' => 110,
            ]);
        $progressBar->advance(4);

        \Modules\Company\Models\ProjectClass::where('name', 'A+ (Besar Plus)')
            ->update([
                'base_point' => 50,
                'point_2_team' => 60,
                'point_3_team' => 69,
                'point_4_team' => 80,
                'point_5_team' => 90,
            ]);
        $progressBar->advance(5);

        \Modules\Company\Models\ProjectClass::where('name', 'A (besar)')
            ->update([
                'base_point' => 40,
                'point_2_team' => 48,
                'point_3_team' => 57,
                'point_4_team' => 64,
                'point_5_team' => 70,
            ]);
        $progressBar->advance(6);

        \Modules\Company\Models\ProjectClass::where('name', 'B+ (Standard Plus)')
            ->update([
                'base_point' => 35,
                'point_2_team' => 42,
                'point_3_team' => 48,
                'point_4_team' => 56,
                'point_5_team' => 65,
            ]);
        $progressBar->advance(7);

        $progressBar->finish();
        $this->info("\nMigration completed successfully!");
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
