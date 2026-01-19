<?php

namespace Tests\Feature\Production;

use Tests\TestCase;
use App\Actions\Hrd\PointRecord;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

use function Pest\Laravel\assertDatabaseHas;

it ('First pic create new record', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    
    $payload = [
        'points' => [
            [
                'uid' => $employee->uid,
                'point' => 10,
                'additional_point' => 5,
                'original_point' => 10,
                'prorate_point' => 15,
                'calculated_prorate_point' => 15,
                'is_special_employee' => 1,
                'tasks' => [1, 2, 3],
            ]
        ]
    ];
    
    // Act
    $result = PointRecord::run($payload, $project->uid, 'production', false);
    
    // Assert
    expect($result)->toBeTrue();
    assertDatabaseHas('employee_point_projects', [
        'project_id' => $project->id,
        'total_point' => 10,
        'additional_point' => 5,
    ]);
});

it ('Second pic update existing record', function () {
    // Arrange - First PIC already submitted
    // Arrange
    $employee = Employee::factory()->create();
    $regularEmployee = Employee::factory()->create();
    $project = Project::factory()->create();
    
    $payload = [
        'points' => [
            [
                'uid' => $employee->uid,
                'point' => 10,
                'additional_point' => 5,
                'original_point' => 10,
                'prorate_point' => 15,
                'calculated_prorate_point' => 15,
                'is_special_employee' => 1,
                'tasks' => [1, 2, 3],
            ]
        ]
    ];

    $payloadRegular = [
        'points' => [
            [
                'uid' => $regularEmployee->uid,
                'point' => 3,
                'additional_point' => 0,
                'original_point' => 3,
                'prorate_point' => 3,
                'calculated_prorate_point' => 3,
                'is_special_employee' => 0,
                'tasks' => [1,2,3],
            ]
        ]
    ];
    
    // Act
    PointRecord::run($payload, $project->uid, 'production', false);
    PointRecord::run($payloadRegular, $project->uid, 'production', true);
    
    // Act - Second PIC submits
    $payload = [
        'points' => [
            [
                'uid' => $employee->uid,
                "point" => 10,
                "additional_point" => 3,
                "original_point" => 10,
                "prorate_point" => 13,
                "calculated_prorate_point" => 13,
                "is_special_employee" => 1,
                'tasks' => [1, 2, 3],
            ]
        ]
    ];
    PointRecord::run($payload, $project->uid, 'production', false);

    $record = \Modules\Hrd\Models\EmployeePointProject::where('project_id', $project->id)
        ->where('employee_point_id', function ($query) use ($employee) {
            $query->select('id')
                ->from('employee_points')
                ->where('employee_id', $employee->id);
        })
        ->first();

    $regularRecord = \Modules\Hrd\Models\EmployeePointProject::where('project_id', $project->id)
        ->where('employee_point_id', function ($query) use ($regularEmployee) {
            $query->select('id')
                ->from('employee_points')
                ->where('employee_id', $regularEmployee->id);
        })
        ->first();
    
    // Assert
    expect($regularRecord->additional_point)->toBe(0);
    expect($regularRecord->total_point)->toBe(3);
    expect($record->additional_point)->toBe(8); // 5 + 3
    expect($record->total_point)->toBe(18); // 10 + 8
});