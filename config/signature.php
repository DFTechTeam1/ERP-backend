<?php

use Modules\Hrd\Models\Employee;

return [
    'available_replacer_column' => [
        'name' => [
            'model' => Employee::class,
            'column' => 'name',
            'key' => 'name',
        ],
        'nickname' => [
            'model' => Employee::class,
            'column' => 'nickname',
            'key' => 'nickname',
        ],
        'email' => [
            'model' => Employee::class,
            'column' => 'email',
            'key' => 'email',
        ],
        'phone' => [
            'model' => Employee::class,
            'column' => 'phone',
            'key' => 'phone',
        ],
        'nik' => [
            'model' => Employee::class,
            'column' => 'id_number',
            'key' => 'nik',
        ],
        'employee_id' => [
            'model' => Employee::class,
            'column' => 'employee_id',
            'key' => 'employee_id',
        ],
        'employee_position_name' => [
            'model' => Employee::class,
            'relation' => 'position:id,name',
            'column' => 'name',
            'key' => 'name',
        ],
    ],
    'master_fullpath' => 'app/public/documents/templates',
    'master_path' => 'documents/templates',
];
