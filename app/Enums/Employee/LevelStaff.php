<?php

namespace App\Enums\Employee;

enum LevelStaff: string
{
    case Manager = 'manager';
    case Lead = 'lead';
    case Staff = 'staff';
    case Junior = 'junior_staff';

    public static function levelStaffOrder()
    {
        return [
            self::Manager,
            self::Lead,
            self::Staff,
            self::Junior
        ];
    }

    public static function generateLevel(string $data)
    {
        if ($data == 'Lead') {
            return self::Lead;
        } else if ($data == 'Manager') {
            return self::Manager;
        } else if ($data == 'Staff') {
            return self::Staff;
        } else if ($data == 'Junior Staff') {
            return self::Junior;
        }
    }
}
