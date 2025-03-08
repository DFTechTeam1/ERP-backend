<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Modules\Company\Models\Position;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;

class PrepareEmployeeMigration implements FromView, ShouldAutoSize
{
    /**
    * @return \Illuminate\Contracts\View\View
    */
    public function view(): View
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

        return view('hrd::prepare-export-employee', compact('all'));
    }
}
