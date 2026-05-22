<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;

class FormatEmployeePhoneNumber extends Command
{
    protected $signature = 'app:format-employee-phone-number {--dry-run : Preview changes without updating the database}';

    protected $description = 'Format employee phone numbers by removing leading zero prefix';

    public function handle(): void
    {
        $isDryRun = $this->option('dry-run');

        $employees = Employee::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '0%')
            ->get(['id', 'phone', 'nickname']);

        if ($employees->isEmpty()) {
            $this->info('No phone numbers need formatting.');

            return;
        }

        $this->info("Found {$employees->count()} phone number(s) to format.");

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be saved.');
            $this->newLine();

            $rows = $employees->map(fn ($employee) => [
                $employee->id,
                $employee->nickname,
                $employee->phone,
                ltrim($employee->phone, '0'),
            ])->toArray();

            $this->table(['ID', 'Nickname', 'Current Phone', 'Formatted Phone'], $rows);

            return;
        }

        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $employee) {
            $formatted = ltrim($employee->phone, '0');
            $employee->update(['phone' => $formatted, 'is_phone_verified' => true]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done. All leading zeros have been removed from employee phone numbers.');
    }
}
