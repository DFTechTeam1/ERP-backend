<?php

/**
 * This job is to get transfer entity schedule for current date
 * If status is 'pending' update the employees data based on transfer history record
 * Notify HR and Employee about the changes
 */

namespace Modules\Hrd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Data\TransferHistory\HistoryData;
use Modules\Hrd\Data\TransferHistory\ValidEmployeeData;
use Spatie\LaravelData\DataCollection;

class CheckTransferEntityScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->processData();
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

    public function processData()
    {
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $transferHistories = \Modules\Hrd\Models\EmployeeTransferHistory::pendingHistories()
                ->pendingHistoriesRelation()
                ->get();
    
            /** @var array<int, DataCollection<ValidEmployeeData>> $failedData */
            $failedData = [];

            /** @var array<int, DataCollection<ValidEmployeeData>> $validData */
            $validData = [];
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
            }

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
        }

    }
}
