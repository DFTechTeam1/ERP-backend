<?php

namespace Modules\Company\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\Division;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\Position;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrateOldDivisionToNewDivision extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'company:migrate-old-division';

    /**
     * The console command description.
     */
    protected $description = 'Migrate old division to new division';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function migrate()
    {
        $employees = array(
            array('id' => '1','name' => 'Wesley Wiyadi','position_id' => '1'),
            array('id' => '2','name' => 'Edwin Chandra Wijaya Ngo','position_id' => '2'),
            array('id' => '3','name' => 'Rudhi Soegiarto','position_id' => '3'),
            array('id' => '4','name' => 'Hutomo Putra Winata','position_id' => '19'),
            array('id' => '5','name' => 'Raja Safrizal Arnindo Attahashi','position_id' => '3'),
            array('id' => '6','name' => 'Angelina Sigit','position_id' => '5'),
            array('id' => '7','name' => 'David Firdaus','position_id' => '5'),
            array('id' => '8','name' => 'Riyadus Solihin','position_id' => '6'),
            array('id' => '9','name' => 'Sucha Aji Nugroho','position_id' => '4'),
            array('id' => '10','name' => 'Giantoro Susilo','position_id' => '6'),
            array('id' => '11','name' => 'Gilang Rizky Al Mizan','position_id' => '4'),
            array('id' => '12','name' => 'Rani Claudia Bitjoli','position_id' => '4'),
            array('id' => '13','name' => 'Tedi Trihardi','position_id' => '7'),
            array('id' => '14','name' => 'Thalia Miranda Soedarmadji','position_id' => '3'),
            array('id' => '15','name' => 'Muhammad Kanza Eka Ghifari','position_id' => '5'),
            array('id' => '16','name' => 'Hafid Asari','position_id' => '4'),
            array('id' => '17','name' => 'Ilyasa Octavianto','position_id' => '4'),
            array('id' => '18','name' => 'Edward Suryapto','position_id' => '5'),
            array('id' => '19','name' => 'Muhamad Nurisya','position_id' => '4'),
            array('id' => '20','name' => 'Fuad Ashari','position_id' => '5'),
            array('id' => '21','name' => 'Thoriq Nur Hidayah','position_id' => '8'),
            array('id' => '22','name' => 'Dinda Nurvianti Partiwi','position_id' => '9'),
            array('id' => '23','name' => 'Devika Tanuwidjaja','position_id' => '8'),
            array('id' => '24','name' => 'Nehemia Lantis Jojo Winarjati','position_id' => '3'),
            array('id' => '25','name' => 'Galih Ayu Indah Triani','position_id' => '10'),
            array('id' => '26','name' => 'Gabriella Marcelina Sunartho','position_id' => '11'),
            array('id' => '27','name' => 'Yoga Pratama Abdi Margo','position_id' => '4'),
            array('id' => '28','name' => 'Isyfi Arief Darmawan','position_id' => '7'),
            array('id' => '29','name' => 'Muhammad Iqbal Jitno Hassan','position_id' => '12'),
            array('id' => '30','name' => 'Fuad Azaim Siraj','position_id' => '7'),
            array('id' => '31','name' => 'Reza Pratama Koestijanto','position_id' => '7'),
            array('id' => '32','name' => 'Nyoman Ariyo Pradana','position_id' => '12'),
            array('id' => '33','name' => 'Ariya Putra Sundava','position_id' => '12'),
            array('id' => '34','name' => 'Sherlynn Yuwono','position_id' => '8'),
            array('id' => '35','name' => 'Eza Muhammad Shofi','position_id' => '4'),
            array('id' => '36','name' => 'Pieter','position_id' => '13'),
            array('id' => '37','name' => 'Charles Eduardo','position_id' => '11'),
            array('id' => '38','name' => 'Vicky Apriyana Firdaus','position_id' => '6'),
            array('id' => '39','name' => 'Ferrel Timothy Sutanto','position_id' => '13'),
            array('id' => '40','name' => 'Dhio Pandji Soemardjo','position_id' => '12'),
            array('id' => '41','name' => 'Erik Wahyu Saputro','position_id' => '15'),
            array('id' => '42','name' => 'Nur Laily Ida Yagshya','position_id' => '4'),
            array('id' => '43','name' => 'Jeremy Fredrick Manasye ','position_id' => '4'),
            array('id' => '44','name' => 'Michelle Lie','position_id' => '4'),
            array('id' => '45','name' => 'Andini Safa Athalia','position_id' => '16'),
            array('id' => '46','name' => 'Dhea Milinia Sefira','position_id' => '17'),
            array('id' => '47','name' => 'Yanuar Andi Rahman','position_id' => '16'),
            array('id' => '48','name' => 'Indra Setya Himawan','position_id' => '7'),
            array('id' => '49','name' => 'Ilham Meru Gumilang','position_id' => '18'),
            array('id' => '50','name' => 'Mochammad Fachrizal Afandi','position_id' => '18'),
            array('id' => '51','name' => 'Rizki Agung Fatchurrahman','position_id' => '19'),
            array('id' => '52','name' => 'Danny Dwi Prasetya','position_id' => '7'),
            array('id' => '53','name' => 'Ridwan Gavyn Ramadhan','position_id' => '7'),
            array('id' => '54','name' => 'Arif Cendekiawan','position_id' => '7'),
            array('id' => '55','name' => 'Maximillian Serafino Suprapto','position_id' => '20'),
            array('id' => '56','name' => 'Bagas Prila Ardian','position_id' => '20'),
            array('id' => '57','name' => 'Ardito Kenanya Hudson Widiono','position_id' => '8'),
            array('id' => '58','name' => 'Fadhil Indiko Putra','position_id' => '19'),
            array('id' => '59','name' => 'Aurellyn Briza','position_id' => '7'),
            array('id' => '60','name' => 'Rahmad Firdaus','position_id' => '7'),
            array('id' => '61','name' => 'Ardian Firmansyah','position_id' => '5'),
            array('id' => '62','name' => 'Avief Reja Satria','position_id' => '3'),
            array('id' => '63','name' => 'Fajar Ramadhan','position_id' => '7'),
            array('id' => '64','name' => 'Muhammad Rizky Al Reza Syamsa Putra','position_id' => '7'),
            array('id' => '65','name' => 'Noval Oktafian','position_id' => '7'),
            array('id' => '66','name' => 'Yumna Syarifah','position_id' => '4')
        );

        $schemas = [
            'Lead Project Manager' => 'Direktur Utama',
            'Head of Creative' => 'Direktur',
            'Project Manager' => 'Project Manager',
            'Animator' => 'Animator',
            'Compositor' => 'Compositor',
            '3D Modeller' => '3D Modeller',
            '3D Generalist' => '3D Generalist',
            'Marcomm Staff' => 'Marcomm Staff',
            'Admin Staff' => 'Finance Admin',
            'HR Generalist' => 'HR Generalist',
            'Lead Marcomm' => 'Lead Marcomm',
            'Operator' => 'Operator',
            'Graphic Designer' => 'Graphic Designer',
            'Marketing Staff' => 'Marketing Staff',
            'IT Technical Support' => 'IT Technical Support',
            'Assistant Project Manager' => 'Assistant Project Manager',
            'HR & TA Admin' => 'HR & GA Admin',
            'Full Stack Developer' => 'Full Stack Developer',
            'Visual Jockey' => 'Operator',
            '3D Animator' => '3D Animator',
        ];

        $all = [];
        foreach ($employees as $employee) {
            $currentPosition = Position::selectRaw('id,name')
                ->where('id', $employee['position_id'])
                ->first();

            // loop schemas
            if (isset($schemas[$currentPosition->name])) {
                $newPositionName = $schemas[$currentPosition->name];
            }

            $newPosition = PositionBackup::selectRaw('id,name')
                ->where('name', $newPositionName)
                ->first();

            $employeeData = Employee::selectRaw('id,name,position_id,employee_id')->where('id', $employee['id'])
                ->first();

            Employee::where('id', $employee['id'])
                ->update(['position_id' => $newPosition->id]);

            $all[] = [
                'id' => $employee['id'],
                'name' => $employee['name'],
                'employee_id' => $employeeData->employee_id,
                'current_position' => [
                    'id' => $currentPosition->id,
                    'name' => $currentPosition->name
                ],
                'new_position' => [
                    'id' => $newPosition ? $newPosition->id : '',
                    'name' => $newPosition ? $newPosition->name : ''
                ]
            ];
        }

        $this->info('Migration is success. ' . count($all) . ' employee(s) has been updated successfully');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // DivisionBackup::truncate();
        // PositionBackup::truncate();

        $divisions = [
            'Manajemen',
            'Production',
            'Entertainment',
            'Production',
            'Marcomm',
            'Finance',
            'Human Resources',
            'Sales',
            'IT'
        ];

        $positions = [
            'Direktur Utama' => 'Manajemen',
            'Direktur' => 'Manajemen',
            'Project Manager' => 'Production',
            'Assistant Project Manager' => 'Production',
            'Operator' => 'Entertainment',
            'Compositor' => 'Production',
            '3D Modeller' => 'Production',
            'Animator' => 'Production',
            'Lead Marcomm' => 'Marcomm',
            'Finance Admin' => 'Finance',
            'Lead Operator' => 'Entertainment',
            'HR Generalist' => 'Human Resources',
            'Marcomm Staff' => 'Marcomm',
            'Marketing Staff' => 'Sales',
            'Graphic Designer' => 'Marcomm',
            'IT Technical Support' => 'IT',
            'HR & GA Admin' => 'Human Resources',
            '3D Generalist' => 'Production',
            'Full Stack Developer' => 'IT',
            '3D Animator' => 'Production',
            '3D Compositor' => 'Production',
            'AI Engineer' => 'IT',
            'Ilustrator' => 'Production'
        ];

        foreach ($divisions as $division) {
            $check = DivisionBackup::select('id')
                ->where('name', $division)
                ->first();
            if (!$check) {
                DivisionBackup::create([
                    'name' => $division
                ]);
            }
        }

        foreach ($positions as $position => $divisionName) {
            $division = DivisionBackup::select('id')
                ->where('name', $divisionName)
                ->first();

            $check = PositionBackup::select('id')
                ->where('name', $position)
                ->first();

            if (!$check) {
                PositionBackup::create([
                    'name' => $position,
                    'division_id' => $division->id
                ]);
            }
        }

        return $this->migrate();

        // migrate employee
        // $schemas = [
        //     'Lead Project Manager' => 'Direktur Utama',
        //     'Head of Creative' => 'Direktur',
        //     'Project Manager' => 'Project Manager',
        //     'Animator' => 'Animator',
        //     'Compositor' => 'Compositor',
        //     '3D Modeller' => '3D Modeller',
        //     '3D Generalist' => '3D Generalist',
        //     'Marcomm Staff' => 'Marcomm Staff',
        //     'Admin Staff' => 'Finance Admin',
        //     'HR Generalist' => 'HR Generalist',
        //     'Lead Marcomm' => 'Lead Marcomm',
        //     'Operator' => 'Operator',
        //     'Graphic Designer' => 'Graphic Designer',
        //     'Marketing Staff' => 'Marketing Staff',
        //     'IT Technical Support' => 'IT Technical Support',
        //     'Assistant Project Manager' => 'Assistant Project Manager',
        //     'HR & TA Admin' => 'HR & GA Admin',
        //     'Full Stack Developer' => 'Full Stack Developer',
        //     'Visual Jockey' => 'Operator',
        //     '3D Animator' => '3D Animator',
        // ];
        // $employees = Employee::selectRaw('id,position_id,name')
        //     ->get();

        // $count = 0;
        // $check = [];
        // foreach ($employees as $key => $employee) {
        //     foreach ($schemas as $currentPosition => $positionName) {
        //         $currentPositionData = Position::select('id')
        //             ->where('name', $currentPosition)
        //             ->first();

        //         $positionData = PositionBackup::select('id')
        //             ->where('name', $positionName)
        //             ->first();

        //         if ($employee->position_id === $currentPositionData->id) {
        //             $nextPosition = $positionData->id;
        //             $check[$key] = [
        //                 'next_position_id' => $nextPosition,
        //                 'current_position_id' => $employee->position_id,
        //                 'id' => $employee->id,
        //                 'name' => $employee->name
        //             ];
        //         }
        //     }

        //     if ($nextPosition >= 0) {
        //         Employee::where('id', $employee->id)
        //             ->update(['position_id' => $nextPosition]);

        //         $count++;
        //     }
        // }
        
        // $this->info('Migration is success. ' . $count . ' employee(s) has been updated successfully');
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
