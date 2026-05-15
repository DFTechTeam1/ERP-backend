<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\WhatsappGroup;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PopulateWhatsappGroup extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:seed-whatsapp-group';

    /**
     * The console command description.
     */
    protected $description = 'Auto create whatsapp group for each PM and populate its team';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function getEmployee(string $name) 
    {
        return Employee::selectRaw('id,is_phone_verified,phone')
        ->where('name', $name)
        ->first();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seed group data for Yanuar');
        $yanuar = $this->getEmployee('Yanuar Andi Rahman');
        $yanuarGroupId = '120363406226726746';

        if ($yanuar) {
            $yanuarGroup = $this->createAndAssignWhatsappGroup(
                groupId: $yanuarGroupId, 
                groupName: 'PM Yanuar',
                employee: $yanuar
            );
            $this->info('Yanuar group is created successfully');
        } else {
            $this->info('Yanuar employee data is not found');
        }

        $rudhi = $this->getEmployee('Rudhi Soegiarto');
        $rudhiGroupId = '120363427017574669';

        if ($rudhi) {
            $rudhiGroup = $this->createAndAssignWhatsappGroup(
                groupId: $rudhiGroupId, 
                groupName: 'PM Rudhi',
                employee: $rudhi
            );
            $this->info('Rudhi group is created successfully');
        } else {
            $this->info('Rudhi employee data is not found');
        }

        $thalia = $this->getEmployee('Thalia Miranda Soedarmadji');
        $thaliaGroupId = '120363426633796295';

        if ($thalia) {
            $thaliaGroup = $this->createAndAssignWhatsappGroup(
                groupId: $thaliaGroupId, 
                groupName: 'PM Thalia',
                employee: $thalia
            );
            $this->info('Thalia group is created successfully');
        } else {
            $this->info('Thalia employee data is not found');
        }

        $nando = $this->getEmployee('Raja Safrizal Arnindo Attahashi');
        $nandoGroupId = '120363424189671488';
        $nandoGroup = $this->createAndAssignWhatsappGroup(
            groupId: $nandoGroupId, 
            groupName: 'PM Nando',
            employee: $nando
        );

        $helmi = $this->getEmployee('Nehemia Lantis Jojo Winarjati');
        $helmiGroupId = '120363409063050641';
        $helmiGroup = $this->createAndAssignWhatsappGroup(
            groupId: $helmiGroupId, 
            groupName: 'VJ - Incharge',
            employee: $helmi
        );
    }

    protected function createAndAssignWhatsappGroup(
        string $groupId,
        string $groupName,
        Employee $employee
    )
    {
        $group = WhatsappGroup::updateOrCreate(
            ['group_id' => $groupId],
            [
                'employee_id' => $employee->id,
                'group_id' => $groupId,
                'group_name' => $groupName,
            ],
        );

        return $group;
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
