<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Data\Resign\ResignData;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\GreatdayResignReason;
use Modules\Hrd\Services\EmployeeService;
use Modules\Hrd\Services\GreatdayService;

class SyncResignEmployeeToERP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-resign-employee
                        {--force : Delete the redundant employees. Without this flag the command only reports what it would do.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Greatday is one step ahead in this state, this command will mark local employee as resign based on Greatday data';

    protected function fetchResignCode()
    {
        return GreatdayResignReason::where('name', 'resign')
            ->firstOrFail();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = app(GreatdayService::class);

        $employees = $service->authedPost('/employees', [
            'page' => 1,
            'limit' => 100,
        ]);

        if ($employees->failed()) {
            $this->error('Failed to fetch greatday employees data');
            exit;
        }

        $greatdayEmployees = $employees->json()['data'];

        $resignedEmployeeIds = collect($greatdayEmployees)
            ->whereNotNull('endDate')
            ->pluck('empNo')
            ->values();

        $targetEmployees = Employee::select(['id', 'uid', 'nickname', 'email', 'employee_id', 'employment_status_id'])
            ->whereIn('employee_id', $resignedEmployeeIds)
            ->whereHas('employmentStatus', function ($query) {
                $query->where('is_terminal', 0);
            })
            ->with([
                'employmentStatus:id,name,is_terminal',
            ])
            ->get();

        $resignReasons = $this->fetchResignCode();

        $rows = [];
        foreach ($targetEmployees as $employee) {
            $matchedEmployee = collect($greatdayEmployees)->firstWhere('empNo', $employee->employee_id);
            $endDate = $matchedEmployee ? date('Y-m-d', strtotime($matchedEmployee['endDate'])) : '-';

            $rows[] = [
                $employee->employee_id,
                $employee->uid,
                $employee->nickname,
                $employee->email,
                $employee->employmentStatus->name,
                $employee->employmentStatus->is_terminal,
                $endDate,
                $resignReasons ? $resignReasons->code : '-',
            ];
        }

        $this->table(
            ['ID', 'UID', 'Nickname', 'Email', 'Employment Status', 'Is Terminal Employment Status?', 'Target Resign Date', 'Resign Code Reason'],
            $rows
        );

        if (! $this->option('force')) {
            return self::SUCCESS;
        }

        // Confirmation
        if (! $this->confirm('Update these employee data?', false)) {
            $this->comment('Aborted. Nothing was updated.');

            return self::SUCCESS;
        }

        // terminate
        $employeeService = app(EmployeeService::class);

        DB::beginTransaction();
        try {
            foreach ($rows as $targetEmployee) {
                $payload = new ResignData(
                    resign_reason_code: $resignReasons->code,
                    resign_date: $targetEmployee[6],
                    remark: 'Resign',
                    sync_greatday: false
                );
                $employeeService->resign($payload, $targetEmployee[1]);
            }
            DB::commit();
            $this->info(count($rows).' employee data has been updated');
        } catch (\Throwable $th) {
            DB::rollBack();

            $this->error($th);
        }
    }

    protected function deleteEmployees() {}
}
