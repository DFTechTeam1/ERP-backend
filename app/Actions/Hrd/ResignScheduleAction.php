<?php

namespace App\Actions\Hrd;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Data\TransferHistory\HistoryData;
use Modules\Hrd\Data\TransferHistory\ValidEmployeeData;
use Spatie\LaravelData\DataCollection;

class ResignScheduleAction
{
    use AsAction;

    public function handle()
    {
        return $this->processData();
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
            $transferHistories = \Modules\Hrd\Models\EmployeeTransferHistory::pendingResign()
                ->pendingHistoriesRelation()
                ->get();
    
            /** @var array<int, DataCollection<ValidEmployeeData>> $failedData */
            $failedData = [];

            /** @var array<int, DataCollection<ValidEmployeeData>> $validData */
            $validData = [];

            // Get employment status that categorize as terminal status
            $terminalStatus = \Modules\Hrd\Models\EmploymentStatus::select('id')
                ->where('is_terminal', 1)
                ->first();

            foreach ($transferHistories as $history) {
                $isValid = true;

                if (! $terminalStatus) {
                    $isValid = false;
                    $failedData[$history->employee->name][] = new ValidEmployeeData(
                        employeeId: $history->employee_id,
                        employeeName: $history->employee->name,
                        reason: 'Terminal Employment Status is not found'
                    );
                }
    
                if ($isValid) {
                    // Update employment transfer history status
                    \Modules\Hrd\Models\EmployeeTransferHistory::where('id', $history->id)
                        ->update([
                            'status' => 'active'
                        ]);

                    // Terminate ERP accounts
                    $userData = \App\Models\User::lockForUpdate()
                        ->where('employee_id', $history->employee_id)
                        ->first();

                    if ($userData) {
                        $userData->email = "resign_{$userData->email}";
                        $userData->save();

                        // Delete
                        $userData->delete();
                    }

                    // Update employment status
                    $employeeData = \Modules\Hrd\Models\Employee::select('id', 'name', 'email', 'position_id', 'employment_status_id')
                        ->lockForUpdate()
                        ->find($history->employee_id);
                    
                    if ($employeeData) {
                        if (!\Illuminate\Support\Str::contains($employeeData->email, 'resign')) {
                            $employeeData->email = "resign_{$employeeData->email}";
                        }
                        $employeeData->employment_status_id = $terminalStatus->id;
                        $employeeData->status = \App\Enums\Employee\Status::Inactive->value;
                        $employeeData->save();

                        // Create employee resign record
                        \Modules\Hrd\Models\EmployeeResign::create([
                            'employee_id' => $employeeData->id,
                            'reason' => 'Reason',
                            'resign_date' => date('Y-m-d'),
                            'current_position_id' => $employeeData->position_id,
                            'current_employee_status' => $employeeData->employment_status_id
                        ]);
                    }

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
            \Modules\Hrd\Jobs\ResignScheduleNotificationToDeveloperJob::dispatch(
                $payloadJob
            )->afterCommit();

            \Illuminate\Support\Facades\DB::commit();
    
            return response()->json([
                'histories' => $transferHistories,
                'failed' => $failedData,
                'validData' => $validData
            ]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => errorMessage($th)
            ]);
        }

    }
}
