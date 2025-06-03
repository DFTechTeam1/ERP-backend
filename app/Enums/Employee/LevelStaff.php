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
            self::Junior,
        ];
    }

    public static function generateLevel(string $data)
    {
        if ($data == 'Lead') {
            return self::Lead;
        } elseif ($data == 'Manager') {
            return self::Manager;
        } elseif ($data == 'Staff') {
            return self::Staff;
        } elseif ($data == 'Junior Staff') {
            return self::Junior;
        }
    }

    public static function generateLabel(string $data)
    {
        if ($data == self::Lead->value) {
            return 'Lead';
        } elseif ($data == self::Manager->value) {
            return 'Manager';
        } elseif ($data == self::Staff->value) {
            return 'Staff';
        } elseif ($data == self::Junior->value) {
            return 'Junior Staff';
        }
    }
}
