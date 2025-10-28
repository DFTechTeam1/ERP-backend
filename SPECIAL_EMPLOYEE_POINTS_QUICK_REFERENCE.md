# Special Employee Points - Quick Reference Guide

## 📋 TL;DR

**Problem**: Multiple PICs award additional points to special employees → Need to accumulate all PICs' contributions

**Solution**: First PIC creates record, subsequent PICs update by adding their additional points

## 🔢 Formula

```
total_point = base_point + (PIC1_additional + PIC2_additional + PIC3_additional + ...)
```

## 📊 Example

| Submission | Action | Base Point | Additional Point | Accumulated Additional | Total Point |
|-----------|--------|------------|------------------|----------------------|-------------|
| PIC 1     | CREATE | 10         | +5               | 5                    | **15**      |
| PIC 2     | UPDATE | 10         | +3               | 8                    | **18**      |
| PIC 3     | UPDATE | 10         | +2               | 10                   | **20**      |

## 💻 Usage

```php
// In your ProjectService where you handle project completion

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
    
    // Handle special employees (with accumulation)
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

## 🎯 Key Points

1. ✅ **First PIC**: Creates new record with initial points
2. ✅ **Other PICs**: Update existing record by adding their additional points
3. ✅ **Base Point**: Never changes (determined by number of tasks)
4. ✅ **Additional Point**: Accumulates from all PICs
5. ✅ **Total Point**: Automatically recalculated on each update
6. ✅ **Tasks**: Deduplicated automatically

## 🔍 How to Check

### Check Database
```sql
-- View special employee points for a specific project
SELECT 
    epp.id,
    e.name as employee_name,
    p.name as project_name,
    epp.original_point as base_point,
    epp.additional_point as accumulated_additional,
    epp.total_point,
    epp.updated_at as last_updated
FROM employee_point_projects epp
JOIN employee_points ep ON ep.id = epp.employee_point_id
JOIN employees e ON e.id = ep.employee_id
JOIN projects p ON p.id = epp.project_id
WHERE p.id = YOUR_PROJECT_ID
AND e.id = YOUR_SPECIAL_EMPLOYEE_ID;
```

### Check Logs
```bash
# View recent special employee point logs
tail -f storage/logs/laravel.log | grep "Special employee point"
```

## ⚠️ Common Mistakes to Avoid

❌ **DON'T** use the regular `handle()` method for special employees
❌ **DON'T** forget to set `is_special_employee = 1` in payload
❌ **DON'T** expect the function to replace additional points (it accumulates!)

✅ **DO** separate special and regular employees before processing
✅ **DO** call `handleSpecialEmployeePoints()` for special employees
✅ **DO** ensure all PICs submit their assessments

## 📞 Troubleshooting

### Issue: Points not accumulating
**Check**: Is `is_special_employee` set to `1` in the payload?

### Issue: Duplicate records created
**Check**: Are you using `handleSpecialEmployeePoints()` instead of `handle()`?

### Issue: Total points incorrect
**Check**: Verify base point matches task count; check logs for calculation details

### Issue: Some PICs' points missing
**Check**: Did all PICs complete their assessment? Check logs for each submission

## 📚 Related Files

- Main logic: `/app/Actions/Hrd/PointRecord.php`
- Request validation: `/Modules/Production/app/Http/Requests/Project/CompleteProject.php`
- Service integration: `/Modules/Production/app/Services/ProjectService.php`
- Full documentation: `/SPECIAL_EMPLOYEE_POINT_HANDLING.md`

## 🔗 Method Signature

```php
public function handleSpecialEmployeePoints(
    array $payload,      // Must contain 'points' array with is_special_employee flag
    string $projectUid,  // Project unique identifier
    string $type         // 'production' or 'entertainment'
): bool                  // Returns true on success, false on error
```

## 📝 Payload Example

```json
{
  "points": [
    {
      "uid": "emp-uuid-123",
      "point": 10,
      "additional_point": 5,
      "calculated_prorate_point": 15,
      "original_point": 10,
      "prorate_point": 15,
      "is_special_employee": 1,
      "tasks": [1, 2, 3, 4, 5]
    }
  ]
}
```

---

**Last Updated**: October 28, 2025  
**Version**: 2.0 (with accumulation support)
