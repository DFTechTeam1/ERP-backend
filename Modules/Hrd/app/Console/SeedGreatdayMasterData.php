<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SeedGreatdayMasterData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:seed-greatday-master';

    /**
     * The console command description.
     */
    protected $description = 'Seed all greatday master data';

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
        $this->info('Seeding greatday timezones ...');

        $service = app(\Modules\Hrd\Services\EmployeeService::class);

        $timezone = $service->getGreatdayTimezones();

        $this->handleNotificationGreatdaySeedingData($timezone, 'timezones');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday religions ...');
        $religions = $service->getGreatdayReligion();

        $this->handleNotificationGreatdaySeedingData($religions, 'religion');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday cost center ...');
        $costCenters = $service->getGreatdayCostCenter();

        $this->handleNotificationGreatdaySeedingData($costCenters, 'cost center');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday job grade ...');
        $jobGrades = $service->getGreatdayJobGrade();

        $this->handleNotificationGreatdaySeedingData($jobGrades, 'job grade');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday companies ...');
        $companies = $service->getGreatdayCompanies();

        $this->handleNotificationGreatdaySeedingData($companies, 'companies');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday employment status ...');
        $employmentStatuses = $service->getGreatdayEmploymentStatus();

        $this->handleNotificationGreatdaySeedingData($employmentStatuses, 'employment status');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday work location ...');
        $workLocations = $service->getGreatdayWorkLocation();

        $this->handleNotificationGreatdaySeedingData($workLocations, 'work location');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday shift pattern ...');
        $shiftPatterns = $service->getGreatdayShiftPattern();

        $this->handleNotificationGreatdaySeedingData($shiftPatterns, 'shift pattern');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday job status ...');
        $jobStatuses = $service->getGreatdayJobStatus();

        $this->handleNotificationGreatdaySeedingData($jobStatuses, 'job status');

        sleep(1); // Add delay to avoid hitting API rate limits

        $this->info('Seeding greatday nationality ...');
        $nationalities = $service->getGreatdayNationality();

        $this->handleNotificationGreatdaySeedingData($nationalities, 'nationality');
    }

    /**
     * Handle notification after seeding greatday master data. If there is an error, it will show error message, if not it will show success message.
     *
     * @param array $response
     * @param string $type
     * @return void
     */
    protected function handleNotificationGreatdaySeedingData(array $response, string $type): void
    {
        if ($response['error']) {
            $this->error("Failed to seed greatday {$type} data. Message: " . $response['message']);
        } else {
            $this->info("Greatday {$type} data seeded successfully.");
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
