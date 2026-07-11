<?php

return [
    'available_replacer_column' => [
        'name' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'name'
        ],
        'nickname' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'nickname'
        ],
        'email' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'email'
        ],
        'phone' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'phone'
        ],
        'nik' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'id_number'
        ],
        'employee_id' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'column' => 'employee_id'
        ],
        'employee_position_name' => [
            'model' => \Modules\Hrd\Models\Employee::class,
            'relation' => 'position:id,name',
            'column' => 'name'
        ],
    ],
    'master_fullpath' => 'app/public/documents/templates',
    'master_path' => 'documents/templates'
];
