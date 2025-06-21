<?php

namespace Modules\Inventory\Rules\EmployeeInventory;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Models\EmployeeInventoryMaster;

class EmployeeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $employeeId = getIdFromUid($value, new Employee);
        $check = EmployeeInventoryMaster::select('id')
            ->where('employee_id', $employeeId)
            ->first();

        if ($check) {
            $fail('auth.employeeExists')->translate();
        }
    }
}
