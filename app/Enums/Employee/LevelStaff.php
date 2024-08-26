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

    public static function generateLabel(string $data)
    {
        if ($data == self::Lead->value) {
            return 'Lead';
        } else if ($data == self::Manager->value) {
            return 'Manager';
        } else if ($data == self::Staff->value) {
            return 'Staff';
        } else if ($data == self::Junior->value) {
            return 'Junior Staff';
        }
    }
}
