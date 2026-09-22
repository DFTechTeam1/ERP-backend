# Performance report — production ↔ entertainment team-move fix

## Symptom

In the performance report Excel (23–24 Aug 2026), Ervin (employee `id = 74`) showed a
**production task from project 284** (`project_date = 2025-02-15`) — a task outside the
report's date range. Happens to anyone who moves between the **production** and
**entertainment** teams.

## Root cause

The production/entertainment distinction is stored on the **wrong entity**.

- `employee_points.type` is **one enum per employee**, written **once** when the employee's
  first point row is created and **never updated**
  (`app/Actions/Hrd/PointRecord.php:48-58` and `:195-205`). Ervin earned points as
  production first, so his row is permanently `type = 'production'`.
- The correct per-project type **is** known at record time (`PointRecord::run(..., 'entertainment')`,
  `ProjectService.php:8697`; `employee_type` in `PointRecordBasedOnReward.php`) but it is
  **discarded** — `->except([... 'employee_type'])` at `app/Actions/Hrd/PointRecordBasedOnReward.php:160`.
- `employee_point_project_details` has **no task-type discriminator** — only a bare `task_id`
  (`...create_employee_point_project_details_table.php:20`). That same `task_id` is resolved
  against **two different tables**: `productionTask → project_tasks`,
  `entertainmentTask → entertainment_task_songs` (`EmployeePointProjectDetail.php:30-38`).
- Reports pick the table purely from the parent `employee_points.type`
  (`NewTemplatePerformanceReportExport.php` and `EmployeePointService::renderEachEmployeePoint`).

So for Ervin, the in-range entertainment detail's `task_id` was looked up in `project_tasks`
(because his type is `production`). `project_tasks` and `entertainment_task_songs` have
overlapping ids, so `task_id = N` resolved to an unrelated **production** task that belonged to
project 284. The date filter never rejected 284 — 284 was never queried; its name arrived through
a mistyped id lookup.

> The earlier schema (`create_employee_point_details_table`, `create_employee_points_table`)
> **had** a per-row `task_type` enum. The Feb 2025 refactor moved `type` up to `employee_points`
> and dropped the discriminator — that refactor introduced this class of bug.

## Immediate fix already applied (no schema change)

`app/Exports/NewTemplatePerformanceReportExport.php` now resolves each task against the
**point-project's own `project_id`** instead of `employee_points.type`, and accepts a task only
when it belongs to that project — so a colliding out-of-range task is rejected. Added `project_id`
to the eager-loaded `productionTask` / `entertainmentTask` selects to support the check.

Test: `tests/Feature/PerformanceReportTeamMoveTest.php` (reproduces the collision). Run it in your
test environment:

```bash
make test-migrate   # only if the schema DB isn't migrated yet
php artisan test tests/Feature/PerformanceReportTeamMoveTest.php
```

> Could not be executed in the Claude sandbox (no pgsql/sqlite driver, no reachable MySQL test DB).

## Audit — find everyone affected (read-only, run before tomorrow)

Lists employees whose point-projects contain details of the *other* type — i.e. team-movers whose
old report is wrong:

```sql
SELECT e.id AS employee_id, e.name, ep.type AS point_type,
       SUM(pt.id IS NOT NULL)  AS production_details,
       SUM(ets.id IS NOT NULL) AS entertainment_details
FROM employee_points ep
JOIN employees e  ON e.id = ep.employee_id
JOIN employee_point_projects epp        ON epp.employee_point_id = ep.id
JOIN employee_point_project_details eppd ON eppd.point_id = epp.id
LEFT JOIN project_tasks pt            ON pt.id  = eppd.task_id AND pt.project_id  = epp.project_id
LEFT JOIN entertainment_task_songs ets ON ets.id = eppd.task_id AND ets.project_id = epp.project_id
GROUP BY e.id, e.name, ep.type
HAVING (ep.type = 'production'    AND entertainment_details > 0)
    OR (ep.type = 'entertainment' AND production_details    > 0);
```

## Long-term structural fix — checklist (recommended: type on `employee_point_projects`)

Put the type on the **work**, not the person. A whole project participation is uniformly one type,
so `employee_point_projects` is the right level.

- [ ] **Migration** — add a nullable discriminator:
      `$table->enum('type', ['production', 'entertainment'])->nullable()->after('project_id');`
      on `employee_point_projects`. Keep it nullable for the backfill, tighten later if desired.
- [ ] **Backfill** existing rows from actual task ownership (authoritative, not from
      `employee_points.type`):
  - [ ] production if a `project_tasks` row exists with `id = detail.task_id` and
        `project_id = epp.project_id`;
  - [ ] entertainment if an `entertainment_task_songs` row matches the same way;
  - [ ] log/park rows that match neither (orphan task ids) for manual review.
- [ ] **Write path** — persist the type instead of dropping it:
  - [ ] `app/Actions/Hrd/PointRecord.php` — set `type` on the `EmployeePointProject::create([...])`
        in both `handleRegularEmployeePoints` and `handleSpecialEmployeePoints` from the `$type` arg.
  - [ ] `app/Actions/Hrd/PointRecordBasedOnReward.php:160` — stop excluding `employee_type`; map it
        onto the point-project's `type`.
- [ ] **Read paths** — read `pointProject->type` (fall back to the project-ownership check while
      backfill is incomplete):
  - [ ] `app/Exports/NewTemplatePerformanceReportExport.php` — the interim ownership check can stay,
        or switch to the stored `type`.
  - [ ] `Modules/Hrd/app/Services/EmployeePointService.php:101-135`
        (`renderEachEmployeePoint`) — **same bug, still unfixed**; it selects relations by
        `employee_points.type`. Load both task relations and pick by the point-project type.
- [ ] **Model** — add `type` to `EmployeePointProject::$fillable`.
- [ ] **Consistency** — decide whether `employee_points.type` stays (informational only) or is
      dropped. If it stays, update it when someone changes teams, but reads must no longer depend on it.
- [ ] **Report duplication (separate, pre-existing)** — an entertainment participant is emitted
      twice in the export: once from the point-project loop and once from the
      `entertainmentTaskSong` grouping block (`NewTemplatePerformanceReportExport.php:100-152`).
      Decide on one source of truth so entertainment rows aren't double-counted.
- [ ] **Tests** — keep `PerformanceReportTeamMoveTest`; add one for `renderEachEmployeePoint`
      and one asserting a fresh entertainment point-project is stored with `type = 'entertainment'`.

## Rollout order

1. Ship the migration (nullable `type`).
2. Backfill + run the audit query; confirm zero unresolved rows.
3. Deploy the write-path changes so new rows carry `type`.
4. Switch reads to `pointProject->type`.
5. (Optional) tighten the column to `NOT NULL` and address the report duplication.
