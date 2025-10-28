# Test Scenarios for Special Employee Points

## Test Case 1: Single PIC Submission
**Setup**: Project with 1 PIC, 1 special employee

### Input
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 8,
    "additional_point": 3,
    "original_point": 8,
    "prorate_point": 11,
    "calculated_prorate_point": 11,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8]
  }]
}
```

### Expected Result
- ✅ New record created in `employee_point_projects`
- ✅ `total_point = 11` (8 + 3)
- ✅ `additional_point = 3`
- ✅ 8 task details created
- ✅ Log: "Special employee point created successfully"

---

## Test Case 2: Two PICs Sequential Submission
**Setup**: Project with 2 PICs, 1 special employee

### First PIC Input
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 10,
    "additional_point": 5,
    "original_point": 10,
    "prorate_point": 15,
    "calculated_prorate_point": 15,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```

### Second PIC Input
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 10,
    "additional_point": 3,
    "original_point": 10,
    "prorate_point": 13,
    "calculated_prorate_point": 13,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```

### Expected Result After PIC 1
- ✅ New record created
- ✅ `total_point = 15` (10 + 5)
- ✅ `additional_point = 5`

### Expected Result After PIC 2
- ✅ Same record updated (NOT new record created)
- ✅ `total_point = 18` (10 + 5 + 3)
- ✅ `additional_point = 8` (5 + 3)
- ✅ No duplicate task details
- ✅ Log: "Special employee point updated successfully"
- ✅ Log shows: old_additional_point: 5, new_additional_point: 8

---

## Test Case 3: Three PICs with Different Additional Points
**Setup**: Project with 3 PICs, 1 special employee

### Submissions
| PIC | Additional Point |
|-----|------------------|
| 1   | 5                |
| 2   | 3                |
| 3   | 2                |

### Expected Progressive Results

| After PIC | Action | Accumulated Additional | Total Point |
|-----------|--------|----------------------|-------------|
| 1         | CREATE | 5                    | 15          |
| 2         | UPDATE | 8                    | 18          |
| 3         | UPDATE | 10                   | 20          |

### Database Check Query
```sql
SELECT 
    additional_point, 
    total_point,
    updated_at
FROM employee_point_projects 
WHERE id = [record_id]
ORDER BY updated_at;
```

**Expected**: Only 1 row, with final values `additional_point=10, total_point=20`

---

## Test Case 4: Special Employee with Zero Additional Points
**Setup**: PIC gives 0 additional points

### Input
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 10,
    "additional_point": 0,
    "original_point": 10,
    "prorate_point": 10,
    "calculated_prorate_point": 10,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```

### Expected Result
- ✅ Record still created/updated
- ✅ `total_point = 10` (10 + 0)
- ✅ `additional_point = 0` (or accumulated if updating)

---

## Test Case 5: Mixed Regular and Special Employees
**Setup**: 1 special employee, 2 regular employees

### Input
```json
{
  "points": [
    {
      "uid": "regular-emp-001",
      "point": 5,
      "additional_point": 1,
      "is_special_employee": 0,
      "tasks": [1, 2, 3, 4, 5]
    },
    {
      "uid": "special-emp-001",
      "point": 10,
      "additional_point": 5,
      "is_special_employee": 1,
      "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
    },
    {
      "uid": "regular-emp-002",
      "point": 7,
      "additional_point": 2,
      "is_special_employee": 0,
      "tasks": [11, 12, 13, 14, 15, 16, 17]
    }
  ]
}
```

### Expected Result
- ✅ Special employee processed with `handleSpecialEmployeePoints()`
- ✅ Regular employees processed with normal `handle()` method
- ✅ All employees have correct points in database

---

## Test Case 6: Duplicate Task Prevention
**Setup**: PIC 2 submits same tasks as PIC 1

### PIC 1 Input
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 5,
    "additional_point": 2,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5]
  }]
}
```

### PIC 2 Input (Same Tasks)
```json
{
  "points": [{
    "uid": "special-emp-001",
    "point": 5,
    "additional_point": 3,
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5]
  }]
}
```

### Expected Result
- ✅ Points updated correctly
- ✅ Task details NOT duplicated (only 5 records in employee_point_project_details)

### Database Check
```sql
SELECT COUNT(*) 
FROM employee_point_project_details 
WHERE point_id = [record_id];
```

**Expected**: COUNT = 5 (not 10)

---

## Test Case 7: Error Handling - Invalid Employee UID
**Setup**: Invalid employee UID provided

### Input
```json
{
  "points": [{
    "uid": "invalid-uid-999",
    "point": 10,
    "additional_point": 5,
    "is_special_employee": 1,
    "tasks": [1, 2, 3]
  }]
}
```

### Expected Result
- ✅ Function returns `false`
- ✅ Error logged with full trace
- ✅ No database changes
- ✅ Other valid employees still processed

---

## Test Case 8: Employee Total Points Across Projects
**Setup**: Special employee works on multiple projects

### Scenario
- Project A: base=10, additional=5, total=15
- Project B: base=8, additional=3, total=11
- Project C: base=12, additional=4, total=16

### Expected Result
```sql
SELECT total_point 
FROM employee_points 
WHERE employee_id = [special_emp_id];
```

**Expected**: `total_point = 42` (15 + 11 + 16)

---

## Testing Commands

### Setup Test Data
```php
// In tinker or seeder
php artisan tinker

// Create test employee
$employee = Employee::create([...]);
$employee->is_special_employee = 1;
$employee->save();

// Create test project
$project = Project::create([...]);
```

### Run Manual Test
```php
use App\Actions\Hrd\PointRecord;

$payload = [
    'points' => [
        [
            'uid' => 'employee-uid-here',
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

$result = PointRecord::run()->handleSpecialEmployeePoints(
    $payload,
    'project-uid-here',
    'production'
);

var_dump($result); // Should be true
```

### Check Logs
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "Special employee"

# Search for specific employee
grep "employee_id: 123" storage/logs/laravel.log
```

### Database Verification
```sql
-- Check point record exists
SELECT * FROM employee_point_projects 
WHERE project_id = ? AND employee_point_id IN (
    SELECT id FROM employee_points WHERE employee_id = ?
);

-- Check task details
SELECT COUNT(*) FROM employee_point_project_details 
WHERE point_id = ?;

-- Check total accumulation
SELECT 
    e.name,
    ep.total_point as employee_total,
    epp.total_point as project_total,
    epp.additional_point as accumulated_additional
FROM employees e
JOIN employee_points ep ON ep.employee_id = e.id
JOIN employee_point_projects epp ON epp.employee_point_id = ep.id
WHERE e.id = ?;
```

---

## Automated Test Template

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Actions\Hrd\PointRecord;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

class SpecialEmployeePointTest extends TestCase
{
    public function test_first_pic_creates_new_record()
    {
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
        $result = PointRecord::run()->handleSpecialEmployeePoints(
            $payload,
            $project->uid,
            'production'
        );
        
        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('employee_point_projects', [
            'project_id' => $project->id,
            'total_point' => 15,
            'additional_point' => 5,
        ]);
    }
    
    public function test_second_pic_updates_existing_record()
    {
        // Arrange - First PIC already submitted
        // ... setup code ...
        
        // Act - Second PIC submits
        // ... test code ...
        
        // Assert
        $this->assertEquals(8, $record->additional_point); // 5 + 3
        $this->assertEquals(18, $record->total_point); // 10 + 8
    }
}
```

---

**Testing Priority:**
1. ✅ Test Case 2 (Two PICs) - Most critical
2. ✅ Test Case 3 (Three PICs) - Verify full accumulation
3. ✅ Test Case 6 (Duplicate Tasks) - Data integrity
4. ✅ Test Case 5 (Mixed Employees) - Integration
5. ✅ Test Case 7 (Error Handling) - Robustness

**Testing Date**: _____________________  
**Tested By**: _____________________  
**Environment**: ☐ Local ☐ Staging ☐ Production  
**Result**: ☐ Pass ☐ Fail  
**Notes**: _____________________
