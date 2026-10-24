<?php

namespace App\Enums\Hrd;

/**
 * Discriminates which table an employee_point_project_details.task_id points to.
 * The point/reward tables store a bare task_id, but that id is resolved against two
 * tables with overlapping ids — project_tasks (production) and entertainment_task_songs
 * (entertainment) — so the type must be stored to resolve it unambiguously.
 *
 * Values match the existing `type` strings used across the point/reward flow.
 */
enum PointTaskType: string
{
    case Production = 'production';
    case Entertainment = 'entertainment';
}
