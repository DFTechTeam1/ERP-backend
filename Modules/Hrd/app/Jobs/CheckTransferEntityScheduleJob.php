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
use Modules\Hrd\Repository\EmployeeTransferHistoryRepository;

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
        $repo = new EmployeeTransferHistoryRepository();

        $nowDate = date('Y-m-d');
        $data = $repo->list(
            select: '*',
            where: "status = 'pending' and effective_date = '{$nowDate}' and LOWER(transfer_type) != 'termination'"
        );

        if ($data->isNotEmpty()) {
            $isNotValid = [];

            foreach ($data as $history) {
                $employmentStatus = \Modules\Hrd\Models\EmploymentStatus::selectRaw('id,code,name')
                    ->find($history->to_employment_status_id);
                $position = \Modules\Company\Models\PositionBackup::selectRaw('id,greatday_code,name')
                    ->find($history->to_position_id);
                $costCenter = \Modules\Hrd\Models\GreatdayCostCenter::selectRaw('id,code,name_en')
                    ->find($history->to_cost_center_id);
                $workLocation = \Modules\Hrd\Models\GreatdayCostCenter::selectRaw('id,name,code')
                    ->find($history->to_work_location_id);


                $isValid = true;

                if (!$employmentStatus) {
                    $isValid = false;
                    $isNotValid[] = [
                        'id' => $history->id,
                        'employee_id' => $history->employee_id,
                        'reason' => 'Missing'
                    ];
                }
                $payload = [
                    'greatday_employment_status' => '',
                    'employment_status_id' => '',
                    'position_id' => '',
                    'boss_id' => '',
                    'greatday_cost_center' => '',
                    'greatday_work_location' => '',
                ];
            }
        }
    }
}
