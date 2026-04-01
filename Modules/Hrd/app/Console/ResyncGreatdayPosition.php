<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ResyncGreatdayPosition extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:resync-greatday-position';

    /**
     * The console command description.
     */
    protected $description = 'Resync greatday position with ERP database';

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
        $service = app(\Modules\Hrd\Services\GreatdayService::class);

        $accessToken = $service->login();

        $positions = \Illuminate\Support\Facades\Http::withToken($accessToken)->post($service->getBaseUrl() . '/company/position', [
            'page' => 1,
            'limit' => 100,
        ]);

        if ($positions->status() < 300) {
            $this->info("Starting to sync divisions ...");

            $progress = $this->output->createProgressBar(count($positions->json()['data']));

            // Insert division first
            $total = count($positions->json()['data']);

            $divisions = [];

            foreach ($positions->json()['data'] as $position) {
                $parentPath = $position['parentPath'];
                $parentId = $position['parentId'];
                $explodePath = explode(',', $parentPath);

                $isDivision = $parentId == 2 ? true : false; // hardcoded

                if ($isDivision) {
                    $currentDivision = \Modules\Company\Models\DivisionBackup::selectRaw('id')
                        ->where('name', $position['posNameEn'])
                        ->first();

                    if (! $currentDivision) {
                        $currentDivision = \Modules\Company\Models\DivisionBackup::create([
                            'name' => $position['posNameEn'],
                        ]);
                    }
                }

                $progress->advance();
            }

            $this->info('');
            $this->info("Starting to sync positions ...");

            // Then insert positions
            foreach ($positions->json()['data'] as $position) {
                $parentPath = $position['parentPath'];
                $explodePath = explode(',', $parentPath);
                $parentId = $position['parentId'];

                if ($parentId != 2) {
                    $divisionId = $parentId;
                    $divisionName = collect($positions->json()['data'])->where('positionId', $divisionId)->first()['posNameEn'] ?? null;
                    $division = \Modules\Company\Models\DivisionBackup::where('name', $divisionName)->first();

                    if ($division) {
                        \Modules\Company\Models\PositionBackup::updateOrCreate(
                            ['name' => $position['posNameEn']],
                            [
                                'division_id' => $division->id,
                                'greatday_code' => $position['posCode']
                            ]
                        );

                        $this->info("Inserted position " . $position['posNameEn'] . " with division is {$division->name}");
                        $this->info('');
                    }
                }

                $progress->advance();
            }

            $progress->finish();

            $this->info("\n{$total} Position data resynced successfully.");
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
