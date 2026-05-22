<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Data\TransferHistory\HistoryData;
use Modules\Hrd\Data\TransferHistory\ValidEmployeeData;
use Spatie\LaravelData\DataCollection;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CheckTransferEntityScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:check-transfer-entity-schedule {--dry-run=0}';

    /**
     * The console command description.
     */
    protected $description = 'Check if there have a transfer history data by today, then process if exists';

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
        $isDryRun = $this->option('dry-run');

        $this->processData($isDryRun);
    }

    protected function assignFailedData(
        array &$failedData,
        \Modules\Hrd\Models\EmployeeTransferHistory $history,
        string $reason
    )
    {
        $failedData[$history->employee->name][] = new ValidEmployeeData(
            employeeId: $history->employee_id,
            employeeName: $history->employee->name,
            reason: $reason
        );
    }

    protected function validateHistoryData(
        bool &$isValid,
        array &$failedData,
        \Modules\Hrd\Models\EmployeeTransferHistory $history,
        \Modules\Hrd\Models\EmploymentStatus | null &$targetEmploymentStatus,
        \Modules\Hrd\Models\GreatdayWorkLocation | null &$targetWorkLocation,
        \Modules\Hrd\Models\GreatdayCostCenter | null &$targetCostCenter,
    )
    {
        // Check target data one by one
        $targetPosition = \Modules\Company\Models\PositionBackup::selectRaw('id')
            ->find($history->to_position_id);
        if (! $targetPosition) {
            $isValid = false;
            $this->assignFailedData($failedData, $history, 'Position not found');
        }

        $targetEmploymentStatus = \Modules\Hrd\Models\EmploymentStatus::select('id', 'code')
            ->find($history->to_employment_status_id);
        if (! $targetEmploymentStatus) {
            $isValid = false;
            $this->assignFailedData($failedData, $history, 'Employment status not found');
        }

        $targetWorkLocation = \Modules\Hrd\Models\GreatdayWorkLocation::select('id', 'code')
            ->find($history->to_work_location_id);
        if (! $targetWorkLocation) {
            $isValid = false;
            $this->assignFailedData($failedData, $history, 'Work location not found');
        }

        $targetCostCenter = \Modules\Hrd\Models\GreatdayCostCenter::select('id', 'code')
            ->find($history->to_cost_center_id);
        if (! $targetCostCenter) {
            $isValid = false;
            $this->assignFailedData($failedData, $history, 'Cost center not found');
        }

        $targetBoss = \Modules\Hrd\Models\Employee::select('id')
            ->find($history->to_boss_id);
        if (! $targetBoss) {
            $isValid = false;
            $this->assignFailedData($failedData, $history, 'Target boss not found');
        }
    }

    public function processData(int $isDryRun)
    {
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $transferHistories = \Modules\Hrd\Models\EmployeeTransferHistory::pendingHistories()
                ->pendingHistoriesRelation()
                ->get();

            if ($isDryRun === 1) {
                $formatTable = [];
                foreach ($transferHistories as $historyTable) {
                    $formatTable[] = [
                        'employee_id' => $historyTable->employee_id,
                        'transfer_type' => $historyTable->transfer_type,
                        'from_position' => $historyTable->from_position_name,
                        'to_position' => $historyTable->to_position_name,
                        'from_cost_center' => $historyTable->from_cost_center_name,
                        'to_cost_center' => $historyTable->to_cost_center_name,
                    ];
                }
                $this->table(
                    headers: [
                        'employee_id', 'transfer_type', 'from_position', 'to_position', 'from_cost_center', 'to_cost_center'
                    ],
                    rows: $formatTable
                );

                exit;
            }
    
            /** @var array<int, DataCollection<ValidEmployeeData>> $failedData */
            $failedData = [];

            /** @var array<int, DataCollection<ValidEmployeeData>> $validData */
            $validData = [];
            $this->info('There have ' . $transferHistories->count() . ' data to update');

            if ($transferHistories->count() > 0) {
                $this->info('Starting to update ...');
            }

            $bar = $this->output->createProgressBar($transferHistories->count());
            $bar->start();
            foreach ($transferHistories as $history) {
                $isValid = true;
                $targetCostCenter = null;
                $targetWorkLocation = null;
                $targetEmploymentStatus = null;
    
                // Check target data one by one sending magic parameters
                $this->validateHistoryData(
                    isValid: $isValid,
                    failedData: $failedData,
                    history: $history,
                    targetEmploymentStatus: $targetEmploymentStatus,
                    targetWorkLocation: $targetWorkLocation,
                    targetCostCenter: $targetCostCenter
                );
    
                if ($isValid) {
                    // Update employment transfer history status
                    \Modules\Hrd\Models\EmployeeTransferHistory::where('id', $history->id)
                        ->update([
                            'status' => 'active'
                        ]);

                    // Update employee table
                    \Modules\Hrd\Models\Employee::where('id', $history->employee_id)
                        ->update([
                            'position_id' => $history->to_position_id,
                            'greatday_cost_center' => $targetCostCenter ? $targetCostCenter->code : null,
                            'greatday_employment_status' => $targetEmploymentStatus ? $targetEmploymentStatus->code : null,
                            'greatday_work_location' => $targetWorkLocation ? $targetWorkLocation->code : null,
                            'boss_id' => $history->to_boss_id
                        ]);

                    $validData[$history->employee->name][] = new ValidEmployeeData(
                        employeeId: $history->employee_id,
                        employeeName: $history->employee->name,
                        reason: 'Success'
                    );
                }

                $bar->advance();
                $this->info('');
            }

            $bar->finish();
            $this->info('Finish update emploee!');

            $payloadJob = new HistoryData(
                validData: $validData,
                failedData: $failedData
            );

            // Send notification to developer for all report, failed and valid
            \Modules\Hrd\Jobs\TransferEntityScheduleNotificationToDeveloperJob::dispatch(
                $payloadJob
            )->afterCommit();

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();

            $this->error('ERROR update: ' . $th->getMessage() . "; Line: " . $th->getLine() . "; file: " . $th->getFile());
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
