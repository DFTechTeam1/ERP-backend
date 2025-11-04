<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateEmployeeAvatar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-employee-avatar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command used to Move employee avatar from static folder in the public folder to the storage folder';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting avatar migration...');

        // Define paths
        $publicPath = public_path('employeeAvatar');
        $storagePath = 'profiles'; // This will be storage/app/public/profiles

        // Check if source folder exists
        if (!File::exists($publicPath)) {
            $this->error("Source folder 'public/employeeAvatar' does not exist!");
            return 1;
        }

        // Create storage directory if it doesn't exist
        if (!Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->makeDirectory($storagePath);
            $this->info("Created directory: storage/app/public/{$storagePath}");
        }

        // Get all employees
        $employees = DB::table('employees')->get();
        
        $successCount = 0;
        $notFoundCount = 0;
        $errorCount = 0;

        $this->info("Processing {$employees->count()} employees...");
        $progressBar = $this->output->createProgressBar($employees->count());

        foreach ($employees as $employee) {
            try {
                // Generate expected filename (lowercase nickname + .jpg)
                $filename = strtolower($employee->nickname) . '.jpg';
                $oldPath = $publicPath . '/' . $filename;
                
                // Check if file exists in public folder
                if (File::exists($oldPath)) {
                    // New storage path
                    $newFilename = $employee->nickname . '_' . time() . '.jpg';
                    $newPath = $storagePath . '/' . $newFilename;
                    
                    // Copy file to storage
                    $fileContents = File::get($oldPath);
                    Storage::disk('public')->put($newPath, $fileContents);
                    
                    // Update database with new path
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update([
                            'avatar' => asset('storage/' . $newPath),
                            'updated_at' => now()
                        ]);

                    // update users table
                    DB::table('users')
                        ->where('employee_id', $employee->id)
                        ->update([
                            'image' => asset('storage/' . $newPath),
                            'updated_at' => now()
                        ]);
                    
                    $successCount++;
                } else {
                    $this->newLine();
                    $this->warn("Avatar not found for: {$employee->nickname} (expected: {$filename})");
                    $notFoundCount++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing {$employee->nickname}: " . $e->getMessage());
                $errorCount++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Migration Summary:");
        $this->info("✅ Successfully migrated: {$successCount}");
        $this->warn("⚠️  Avatar not found: {$notFoundCount}");
        $this->error("❌ Errors: {$errorCount}");

        // Ask for confirmation before deleting public folder
        if ($successCount > 0) {
            if ($this->confirm('Do you want to delete the public/employeeAvatar folder?', true)) {
                File::deleteDirectory($publicPath);
                $this->info("✅ Deleted public/employeeAvatar folder");
            } else {
                $this->info("⏭️  Skipped folder deletion");
            }
        }

        $this->newLine();
        $this->info('Migration completed!');
        $this->info("Don't forget to run: php artisan storage:link");

        return 0;
    }
}
