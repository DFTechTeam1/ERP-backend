# Special Employee Point Handling Documentation

## Problem Statement

When a project has multiple PICs (Person In Charge), each PIC needs to submit their assessment individually. Special employees appear in every PIC's assessment form. Each PIC can award different additional points to special employees based on their individual assessment.

### Challenge
- Prevent duplicate base point records for special employees
- Allow multiple PICs to contribute their own additional points
- Accumulate additional points from all PICs while maintaining a single record per special employee per project

## Solution

A new method `handleSpecialEmployeePoints()` has been added to the `PointRecord` action class to handle special employee points with proper accumulation of additional points from multiple PICs.

## How It Works

### Flow Diagram
```
PIC 1 submits assessment (additional_point: 5)
    ↓
Backend receives payload with is_special_employee flag
    ↓
Check if special employee already has points for this project
    ↓
No → CREATE record: point=10, additional_point=5, total_point=15
    ↓
PIC 2 submits assessment (additional_point: 3)
    ↓
Check if special employee already has points for this project
    ↓
Yes → UPDATE record: additional_point=5+3=8, total_point=10+8=18
    ↓
PIC 3 submits assessment (additional_point: 2)
    ↓
Yes → UPDATE record: additional_point=8+2=10, total_point=10+10=20
```

### Point Calculation Logic

**Formula**: `total_point = base_point + accumulated_additional_point`

**Example with 3 PICs:**
- Base point (from tasks): 10
- PIC 1 additional point: 5
- PIC 2 additional point: 3  
- PIC 3 additional point: 2

**Result:**
- First submission: `total_point = 10 + 5 = 15`
- Second submission: `total_point = 10 + (5 + 3) = 18`
- Final submission: `total_point = 10 + (5 + 3 + 2) = 20`

### Key Features

1. **Single Record Per Project**: One `employee_point_projects` record per special employee per project
2. **Accumulative Additional Points**: Each PIC's additional points are added together
3. **Automatic Recalculation**: Total points are recalculated with each PIC submission
4. **Task Detail Deduplication**: Prevents duplicate task entries when updating
5. **Comprehensive Logging**: Logs both creation and update operations with detailed information
6. **Error Handling**: Comprehensive try-catch with detailed error logging
7. **Data Integrity**: Maintains accurate total points across all projects

## Usage

### Method Signature
```php
public function handleSpecialEmployeePoints(
    array $payload, 
    string $projectUid, 
    string $type
): bool
```

### Parameters
- `$payload`: Array containing points data with `is_special_employee` flag
- `$projectUid`: The unique identifier of the project
- `$type`: Point type ('production' or 'entertainment')

### Example Implementation in Controller/Service

```php
use App\Actions\Hrd\PointRecord;

// In your ProjectService or Controller
public function completeProject($projectUid, $data)
{
    // First, handle special employees separately
    if (!empty($data['points'])) {
        PointRecord::run()->handleSpecialEmployeePoints(
            payload: $data,
            projectUid: $projectUid,
            type: 'production'
        );
        
        // Then handle regular employees with existing handle() method
        // Filter out special employees from payload
        $regularEmployeesPayload = [
            'points' => array_filter($data['points'], function($point) {
                return !isset($point['is_special_employee']) || $point['is_special_employee'] != 1;
            })
        ];
        
        if (!empty($regularEmployeesPayload['points'])) {
            PointRecord::run(
                $regularEmployeesPayload,
                $projectUid,
                'production'
            );
        }
    }
}
```

### Alternative: Unified Approach

You can also modify the calling code to separate special and regular employees:

```php
if (!empty($data['points'])) {
    // Separate special and regular employees
    $specialEmployees = [];
    $regularEmployees = [];
    
    foreach ($data['points'] as $point) {
        if (isset($point['is_special_employee']) && $point['is_special_employee'] == 1) {
            $specialEmployees[] = $point;
        } else {
            $regularEmployees[] = $point;
        }
    }
    
    // Handle special employees (with duplicate prevention)
    if (!empty($specialEmployees)) {
        PointRecord::run()->handleSpecialEmployeePoints(
            ['points' => $specialEmployees],
            $projectUid,
            'production'
        );
    }
    
    // Handle regular employees (normal flow)
    if (!empty($regularEmployees)) {
        PointRecord::run(
            ['points' => $regularEmployees],
            $projectUid,
            'production'
        );
    }
}
```

## Real-World Example

### Scenario
Project "Music Video Production" has 3 PICs and 1 special employee (Video Editor)

**Base Points from Tasks**: 10 points (worked on 10 tasks)

#### First PIC Assessment (Creative Director)
```json
{
  "points": [{
    "uid": "video-editor-001",
    "point": 10,
    "additional_point": 5,  // Excellent creativity
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```
**Result**: CREATE record with `total_point = 15` (10 base + 5 additional)

#### Second PIC Assessment (Technical Director)
```json
{
  "points": [{
    "uid": "video-editor-001",
    "point": 10,
    "additional_point": 3,  // Great technical skills
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```
**Result**: UPDATE record with `total_point = 18` (10 base + 5 + 3 = 18)

#### Third PIC Assessment (Production Manager)
```json
{
  "points": [{
    "uid": "video-editor-001",
    "point": 10,
    "additional_point": 2,  // Good time management
    "is_special_employee": 1,
    "tasks": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
  }]
}
```
**Result**: UPDATE record with `total_point = 20` (10 base + 5 + 3 + 2 = 20)

### Final Database State

**employee_point_projects table:**
| id | employee_point_id | project_id | total_point | additional_point | original_point |
|----|-------------------|------------|-------------|------------------|----------------|
| 1  | 101               | 5          | 20          | 10               | 10             |

**Summary:**
- ✅ Single record for special employee
- ✅ All three PICs' additional points accumulated (5 + 3 + 2 = 10)
- ✅ Final total accurately reflects all contributions (10 + 10 = 20)

## Payload Structure

```json
{
  "feedback": "Great work!",
  "points": [
    {
      "uid": "employee-uid-123",
      "point": 10,
      "additional_point": 2,
      "calculated_prorate_point": 12,
      "original_point": 10,
      "prorate_point": 12,
      "is_special_employee": 1,  // This flag identifies special employees
      "tasks": [1, 2, 3]
    },
    {
      "uid": "employee-uid-456",
      "point": 8,
      "additional_point": 0,
      "calculated_prorate_point": 8,
      "original_point": 8,
      "prorate_point": 8,
      "is_special_employee": 0,  // Regular employee
      "tasks": [4, 5]
    }
  ]
}
```

## Database Structure

### Tables Involved
1. **employee_points**: Main point record per employee
2. **employee_point_projects**: Points per employee per project
3. **employee_point_project_details**: Task details for each point record

### Relationships
```
employee_points (1) → (many) employee_point_projects (1) → (many) employee_point_project_details
```

## Logging

### Success Log - Record Created
```
[2025-10-28 10:30:15] Special employee point created successfully
- employee_id: 123
- project_id: 456
- point_project_id: 789
- base_point: 10
- additional_point: 5
- total_point: 15
```

### Success Log - Record Updated
```
[2025-10-28 11:45:22] Special employee point updated successfully
- employee_id: 123
- project_id: 456
- point_project_id: 789
- old_additional_point: 5
- new_additional_point: 8
- new_total_point: 18
```

### Error Log
```
[2025-10-28 12:00:00] Error recording special employee points
- error: Error message
- trace: Stack trace
- project_uid: project-uid-123
```

## Testing Checklist

- [ ] Test with single PIC submitting assessment with additional points
- [ ] Test with 2 PICs submitting sequentially with different additional points
- [ ] Test with 3+ PICs submitting sequentially
- [ ] Verify first PIC creates new record correctly
- [ ] Verify subsequent PICs update existing record (not create duplicate)
- [ ] Verify additional points are accumulated correctly
- [ ] Verify total_point calculation is accurate (base + accumulated additional)
- [ ] Verify task details are not duplicated on updates
- [ ] Check that regular employees are not affected
- [ ] Verify employee total points across all projects are updated correctly
- [ ] Check log files for proper create/update entries
- [ ] Test error handling with invalid employee UID
- [ ] Test error handling with invalid project UID
- [ ] Test with special employee having additional_point = 0
- [ ] Test concurrent submissions from multiple PICs

## Files Modified

- `/app/Actions/Hrd/PointRecord.php` - Added new methods for special employee handling

## Methods Added

1. **`handleSpecialEmployeePoints()`** - Main method to handle special employee points
   - Creates new record for first PIC submission
   - Updates existing record for subsequent PIC submissions
   - Accumulates additional points from all PICs
   - Prevents duplicate records while allowing point accumulation

2. **`checkExistingSpecialEmployeePoint()`** - Helper to check if record already exists
   - Returns existing record if found
   - Returns null if no record exists

3. **`updateEmployeeTotalPoint()`** - Helper to recalculate total points for an employee
   - Sums all project points for the employee
   - Updates the main employee_points record

## Important Notes

- The existing `handle()` method remains unchanged to maintain backward compatibility
- Special employees should be handled separately from regular employees in the calling code
- The `is_special_employee` flag must be present in the payload and set to `1` for special employees
- Each PIC's `additional_point` is accumulated, not replaced
- Base `point` remains constant; only `additional_point` accumulates
- Task details are deduplicated automatically when updating
- Log files can be monitored at `storage/logs/laravel.log` for tracking point recording activities

## Edge Cases Handled

1. **Zero Additional Points**: If a PIC gives 0 additional points, the record is still updated to ensure all PICs are accounted for
2. **Duplicate Tasks**: Task details are checked for existence before insertion to prevent duplicates
3. **Concurrent Submissions**: Database-level constraints should handle concurrent updates; consider adding transaction locks if needed
4. **Missing Fields**: The function handles missing optional fields gracefully with proper error logging
