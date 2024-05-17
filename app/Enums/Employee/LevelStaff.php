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
}
