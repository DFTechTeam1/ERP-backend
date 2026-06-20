# NewTemplatePerformanceReportExport

`app/Exports/NewTemplatePerformanceReportExport.php`

A queued Laravel Excel export that builds an employee **performance / point report** for a given date range and a requesting user. It pulls per-project point records, normalizes both **production** and **entertainment** point types into one structure, renders them through a Blade view, applies spreadsheet styling, and finally notifies the requesting user (via `ExportImportService`) with a download link.

---

## Class overview

```php
class NewTemplatePerformanceReportExport
    implements FromView, ShouldAutoSize, WithEvents, ShouldQueue
```

| Interface / Trait | Purpose |
|-------------------|---------|
| `FromView` | The sheet is built from a Blade view (an HTML table), not from an array/collection. |
| `ShouldAutoSize` | Auto-sizes columns to fit content. |
| `WithEvents` | Hooks `AfterSheet` to apply styling (freeze pane, filter, borders, bold, alignment) and to send the "ready" notification. |
| `ShouldQueue` | The export runs as a **queued job** (heavy/long-running, async). |
| `Exportable` (trait) | Provides the `->store()` / `->queue()` helpers used to dispatch the export. |

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$startDate` | `string` | Range start, matched against `projects.project_date`. |
| `$endDate` | `string` | Range end, matched against `projects.project_date`. |
| `$userId` | `int` | The user who requested the export; receives the success/failure notification. |
| `$filepath` | `string` | Public URL/path of the generated file, embedded in the success notification link. |

### Constructor

```php
public function __construct(string $startDate = '', string $endDate = '', int $userId, string $filepath)
```

Stores the four inputs on the instance. (Note: ordering is slightly unconventional — `$userId`/`$filepath` are required but declared after the defaulted date strings.)

---

## Functions / methods

### `view(): View`

The core of the export. Steps:

1. **Raise limits** — sets `memory_limit` to `1024M` and logs current memory limit, usage, and max execution time (large reports are memory-heavy).
2. **Instantiate repositories** — `EmployeeRepository` (instantiated but not used in the current logic) and `EmployeePointProjectRepository`.
3. **Query point projects** via `$pointProjectRepo->list(...)` with:
   - **select**: `id, employee_point_id, project_id, total_point, additional_point, calculated_prorate_point, prorate_point, original_point`
   - **relation**: deep eager-loading of project, PICs, entertainment songs, feedbacks, point details, and employee/position (see [Tables & relationships](#tables--relationships)).
   - **whereHas**: only projects whose `project_date` is `BETWEEN startDate AND endDate`.
4. **Build `$output`** (keyed by project name). For each point-project record:
   - Determine `type` from `employeePoint->type` (`production` or `entertainment`).
   - Collect `$tasks`:
     - `production` → `details[].productionTask.name`
     - `entertainment` → `details[].entertainmentTask.song.name`
   - Collect `$pics` from the project's `personInCharges[].employee.name`.
   - Push a normalized row (tasks, points breakdown, employee, position, pics, feedbacks).
5. **Build `$entertainmentList`** — for projects that have `entertainmentTaskSong` rows, group those songs **by `employee_id`** to produce an extra per-employee entertainment summary (1 point per employee, songs concatenated). Feedbacks are attached as `nickname: feedback`.
6. **Merge** `$entertainmentList` into `$output` by project name (append if the project already exists, otherwise create it).
7. **Log** the assembled arrays and **return** the view `hrd::new-export-performance-report` with `['points' => $output]`.

#### Row shape (each item inside `$output[projectName]`)

| Key | Source | Meaning |
|-----|--------|---------|
| `tasks` | imploded task names | Work items done (comma-separated). |
| `total_tasks` | count of tasks | Number of tasks. |
| `point` | `total_point - additional_point` | Base point (excludes bonus). |
| `additional_point` | `additional_point` | Bonus point. |
| `calculated_prorate_point` | `calculated_prorate_point` | Prorate value computed when project hits max-project cap. |
| `prorate_point` | `prorate_point` | Prorate point applied. |
| `original_point` | `original_point` | Raw point (1 task = 1 point). |
| `total_point` | `total_point` | Final total point. |
| `project_name` | project name | Event/client project name. |
| `employee_name` | employee name | The scored employee. |
| `pics` | imploded PIC names | Project PMs / persons-in-charge. |
| `position` | employee position name (or `-`) | Employee's role. |
| `feedbacks` | mapped collection | List of `nickname: feedback` strings. |

> The entertainment summary rows use the same keys but hardcode `point = 1` and zero out the prorate/total fields, since they represent a per-employee song-count summary rather than a scored point record.

### `registerEvents(): array`

Returns one handler keyed by `AfterSheet::class`. After the sheet is written it:

- **Freezes** panes at `C1` (keeps columns A–B and the header visible while scrolling).
- Adds an **auto-filter** across `A1:J1`.
- Applies **thin black borders** to the full range `A1:J{lastRow}`.
- **Bolds** the header rows `A1:J2`.
- **Vertically centers** columns A–B and column J across all rows.
- Calls `ExportImportService::handleSuccessProcessing(...)` to notify the user (`area: finance`) that the report is ready, embedding the download link (`$this->filepath`).

### `failed(\Throwable $exception): void`

Queue failure callback. Calls `ExportImportService::handleErrorProcessing(...)` to notify the user (`area: finance`) that the export failed, passing the exception message.

---

## Tables & relationships

Root model: **`Modules\Hrd\Models\EmployeePointProject`** → table **`employee_point_projects`**.

| Relation chain (as used in `view()`) | Model | Table | Join key | Key columns used |
|--------------------------------------|-------|-------|----------|------------------|
| *(root)* | `EmployeePointProject` | `employee_point_projects` | — | `id, employee_point_id, project_id, total_point, additional_point, calculated_prorate_point, prorate_point, original_point` |
| `project` | `Production\Project` | `projects` | `project_id` | `id, name, project_date` |
| `project.personInCharges` | `ProjectPersonInCharge` | `project_person_in_charges` | `project_id` | `id, project_id, pic_id` |
| `project.personInCharges.employee` | `Hrd\Employee` | `employees` | `pic_id` | `id, name` |
| `project.entertainmentTaskSong` | `EntertainmentTaskSong` | `entertainment_task_songs` | `project_id` | `id, project_id, employee_id, project_song_list_id` |
| `project.entertainmentTaskSong.song` | `ProjectSongList` | `project_song_lists` | `project_song_list_id` | `id, name` |
| `project.entertainmentTaskSong.employee` | `Hrd\Employee` | `employees` | `employee_id` | `id, name, position_id` |
| `project.entertainmentTaskSong.employee.position` | `PositionBackup` | `position_backups` | `position_id` | `id, name` |
| `project.feedbacks` | `ProjectFeedback` | `project_feedbacks` | `project_id` | `id, project_id, pic_id, feedback` |
| `project.feedbacks.pic` | `Hrd\Employee` | `employees` | `pic_id` | `id, nickname` |
| `details` | `EmployeePointProjectDetail` | `employee_point_project_details` | `point_id` | `id, point_id, task_id` |
| `details.productionTask` | `Production\ProjectTask` | `project_tasks` | `task_id` | `id, name` |
| `details.entertainmentTask` | `EntertainmentTaskSong` | `entertainment_task_songs` | `task_id` | `id, project_song_list_id` |
| `details.entertainmentTask.song` | `ProjectSongList` | `project_song_lists` | `project_song_list_id` | `id, name` |
| `employeePoint` | `Hrd\EmployeePoint` | `employee_points` | `employee_point_id` | `id, type, employee_id` |
| `employeePoint.employee` | `Hrd\Employee` | `employees` | `employee_id` | `id, name, position_id` |
| `employeePoint.employee.position` | `PositionBackup` | `position_backups` | `position_id` | `id, name` |

### Notes on key fields

- **`employee_points.type`** — enum `production | entertainment`. Drives which task source is read in `view()`.
- **`employee_point_project_details.task_id`** — polymorphic-ish: resolved either as a `productionTask` (`project_tasks`) or an `entertainmentTask` (`entertainment_task_songs`) depending on point type.
- Filtering happens on **`projects.project_date`** via the `whereHas` on the `project` relation.

### Repository entry point

```php
EmployeePointProjectRepository::list(
    string $select = '*',
    string $where = '',
    array $relation = [],
    array $whereHas = []   // each: ['relation' => string, 'query' => string]
): \Illuminate\Database\Eloquent\Collection
```

---

## Output format expectation

- **Format:** Excel spreadsheet (`.xlsx`) generated from the Blade view `Modules/Hrd/resources/views/new-export-performance-report.blade.php`.
- **Delivery:** Stored at `$filepath`; the user (`$userId`) is notified via `ExportImportService` with a download link. Failures notify the same user with the error message.
- **Grouping:** One block per **project** (event/client). Multiple employees appear per project; project-level cells (No, project name, point breakdown, feedbacks) use **rowspan**.

### Columns (A–J)

| Col | Header (Bahasa) | Meaning | Source field |
|-----|-----------------|---------|--------------|
| A | No | Row/sequence number (rowspan per project) | loop counter |
| B | Nama Event / Klien | Project / client name (rowspan) | `project_name` |
| C | Nama PM / PIC | Project PMs / persons-in-charge | `pics` |
| D | Nama Karyawan | Scored employee | `employee_name` |
| E | Tugas | Employee position/role | `position` |
| F | Poin | Final total point | `total_point` |
| G | Detail Pekerjaan | Task list (comma-separated) | `tasks` |
| H | *(spacer)* | Blank | — |
| I | Point Breakdown | Multi-line breakdown: prorate status, `prorate_point`, `original_point`, `additional_point`, `calculated_prorate_point`, `total_point`, `total_tasks` (rowspan) | point fields |
| J | Feedbacks | `nickname: feedback` lines (rowspan) | `feedbacks` |

### Styling (applied in `AfterSheet`)

- Freeze pane at `C1`; auto-filter on `A1:J1`.
- Thin black borders across `A1:J{lastRow}`.
- Bold header rows `A1:J2`.
- Vertical-center alignment for columns A–B and J.
- Auto-sized columns (`ShouldAutoSize`).
```