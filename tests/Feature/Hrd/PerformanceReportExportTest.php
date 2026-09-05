<?php

use App\Exports\NewTemplatePerformanceReportExport;
use App\Models\User;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectFeedback;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Models\ProjectTask;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use function Pest\Laravel\actingAs;

/**
 * PerformanceReportController@export -> PerformanceReportService::export().
 *
 * export() itself only resolves the date range, builds a signed download URL and QUEUES
 * NewTemplatePerformanceReportExport - so it is covered with Excel::fake()/assertQueued().
 *
 * The point rendering the module owner cares about lives in the export's view()
 * (a FromView export of `hrd::new-export-performance-report`), driven by employee_point_projects:
 *   - column F "Poin"           = total_point
 *   - column G                  = the task list
 *   - column I "Point Breakdown"= Original / Additional / Total point lines
 * So the second block renders the REAL .xlsx and reads the cells back with PhpSpreadsheet.
 *
 * registerEvents() (AfterSheet) fires a Pusher notification, which is a network side effect
 * unrelated to point rendering, so the render helper uses a subclass that skips events while
 * keeping view() intact.
 */
function perfService(): PerformanceReportService
{
    return app(PerformanceReportService::class);
}

/**
 * Seed one production employee_point_project (with its task details) for a project.
 *
 * @param  array<int, string>  $taskNames
 * @return array{0: Project, 1: Employee, 2: EmployeePointProject}
 */
function perfSeedProductionPoint(
    string $projectName,
    string $employeeName,
    string $projectDate,
    int $totalPoint,
    int $additionalPoint,
    array $taskNames
): array {
    $project = Project::factory()->create([
        'name' => $projectName,
        'project_date' => $projectDate,
    ]);

    $employee = Employee::factory()->create(['name' => $employeeName]);

    $point = EmployeePoint::create([
        'employee_id' => $employee->id,
        'total_point' => $totalPoint,
        'type' => 'production',
    ]);

    $pointProject = EmployeePointProject::create([
        'employee_point_id' => $point->id,
        'project_id' => $project->id,
        'total_point' => $totalPoint,
        'additional_point' => $additionalPoint,
        'prorate_point' => 0,
        'calculated_prorate_point' => 0,
        'original_point' => $totalPoint - $additionalPoint,
    ]);

    foreach ($taskNames as $name) {
        $task = ProjectTask::factory()->create(['project_id' => $project->id, 'name' => $name]);
        EmployeePointProjectDetail::create(['point_id' => $pointProject->id, 'task_id' => $task->id]);
    }

    return [$project, $employee, $pointProject];
}

/**
 * Create an entertainment task song (with its ProjectSongList) that an
 * employee_point_project_detail can point to via task_id.
 */
function perfMakeEntertainmentSong(Project $project, Employee $employee, string $songName): EntertainmentTaskSong
{
    $song = ProjectSongList::create([
        'uid' => (string) Str::uuid(),
        'project_id' => $project->id,
        'name' => $songName,
        'created_by' => 1,
    ]);

    return EntertainmentTaskSong::create([
        'project_song_list_id' => $song->id,
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'status' => 1,
    ]);
}

/**
 * Render the report to a real .xlsx (events skipped) and return the active worksheet.
 */
function perfRenderSheet(string $startDate, string $endDate): Worksheet
{
    $export = new class($startDate, $endDate, 1, 'http://localhost/download') extends NewTemplatePerformanceReportExport
    {
        public function registerEvents(): array
        {
            return [];
        }
    };

    $binary = Excel::raw($export, Maatwebsite\Excel\Excel::XLSX);

    $path = tempnam(sys_get_temp_dir(), 'perf_report_').'.xlsx';
    file_put_contents($path, $binary);

    return IOFactory::load($path)->getActiveSheet();
}

describe('PerformanceReportService::export', function () {
    it('queues the export for the given date range and returns the async response', function () {
        Excel::fake();
        actingAs(User::factory()->create());

        $response = perfService()->export([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'all_employee' => true,
        ]);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['path'])->toContain('hrd/performance_report_2026-01-01_2026-01-31.xlsx');

        Excel::assertQueued('hrd/performance_report_2026-01-01_2026-01-31.xlsx', 'public');
    });

    it('falls back to the default period when no start date is given', function () {
        Excel::fake();
        actingAs(User::factory()->create());

        $response = perfService()->export([
            'start_date' => null,
            'end_date' => null,
            'all_employee' => true,
        ]);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['path'])->toContain('hrd/performance_report_');
    });
});

describe('NewTemplatePerformanceReportExport point rendering', function () {
    it('renders point, additional and total point into the excel for a production project', function () {
        perfSeedProductionPoint(
            projectName: 'Grand Wedding',
            employeeName: 'Budi Santoso',
            projectDate: '2026-01-15',
            totalPoint: 5,       // 2 base tasks + 3 additional
            additionalPoint: 3,
            taskNames: ['Compositing', 'Animating'],
        );

        $sheet = perfRenderSheet('2026-01-01', '2026-01-31');

        // row 1 = title banner, row 2 = header, row 3 = the single data row
        expect((string) $sheet->getCell('B3')->getValue())->toContain('Grand Wedding') // event
            ->and((string) $sheet->getCell('D3')->getValue())->toContain('Budi Santoso'); // employee

        // task list (F) + task count (G)
        $tasks = (string) $sheet->getCell('F3')->getValue();
        expect($tasks)->toContain('Compositing')
            ->and($tasks)->toContain('Animating')
            ->and((int) $sheet->getCell('G3')->getValue())->toBe(2);

        // points now live in dedicated, readable columns: base / additional / total
        expect((int) $sheet->getCell('H3')->getValue())->toBe(2)  // Poin Dasar
            ->and((int) $sheet->getCell('I3')->getValue())->toBe(3)  // Poin Tambahan
            ->and((int) $sheet->getCell('J3')->getValue())->toBe(5); // Total Poin
    });

    it('renders total point equal to base point when there is no additional point', function () {
        perfSeedProductionPoint(
            projectName: 'Corporate Gala',
            employeeName: 'Sri Wahyuni',
            projectDate: '2026-01-10',
            totalPoint: 4,
            additionalPoint: 0,
            taskNames: ['Finalize'],
        );

        $sheet = perfRenderSheet('2026-01-01', '2026-01-31');

        expect((int) $sheet->getCell('H3')->getValue())->toBe(4)  // Poin Dasar
            ->and((int) $sheet->getCell('I3')->getValue())->toBe(0)  // Poin Tambahan
            ->and((int) $sheet->getCell('J3')->getValue())->toBe(4); // Total Poin
    });

    it('renders song names in the tasks column for an entertainment-type point project', function () {
        $project = Project::factory()->create(['name' => 'Music Festival', 'project_date' => '2026-01-18']);
        $employee = Employee::factory()->create(['name' => 'Rangga Putra']);

        // The songs belong to a separate project so THIS project's own entertainmentTaskSong list
        // stays empty (that list drives a secondary block in the view we are not exercising here).
        // The entertainment branch resolves task names from each detail's entertainmentTask.song.
        $songProject = Project::factory()->create();
        $songA = perfMakeEntertainmentSong($songProject, $employee, 'Bohemian Rhapsody');
        $songB = perfMakeEntertainmentSong($songProject, $employee, 'Sweet Child O Mine');

        $point = EmployeePoint::create([
            'employee_id' => $employee->id,
            'total_point' => 6,
            'type' => 'entertainment',
        ]);
        $pointProject = EmployeePointProject::create([
            'employee_point_id' => $point->id,
            'project_id' => $project->id,
            'total_point' => 6,     // 4 base + 2 additional
            'additional_point' => 2,
            'prorate_point' => 0,
            'calculated_prorate_point' => 0,
            'original_point' => 4,
        ]);
        EmployeePointProjectDetail::create(['point_id' => $pointProject->id, 'task_id' => $songA->id]);
        EmployeePointProjectDetail::create(['point_id' => $pointProject->id, 'task_id' => $songB->id]);

        $sheet = perfRenderSheet('2026-01-01', '2026-01-31');

        expect((string) $sheet->getCell('B3')->getValue())->toContain('Music Festival')
            ->and((string) $sheet->getCell('D3')->getValue())->toContain('Rangga Putra');

        // tasks come from the SONG names (entertainment branch), not production task names
        $tasks = (string) $sheet->getCell('F3')->getValue();
        expect($tasks)->toContain('Bohemian Rhapsody')
            ->and($tasks)->toContain('Sweet Child O Mine');

        // point rendering still holds for the entertainment type
        expect((int) $sheet->getCell('G3')->getValue())->toBe(2)  // Jumlah Tugas
            ->and((int) $sheet->getCell('H3')->getValue())->toBe(4)  // Poin Dasar
            ->and((int) $sheet->getCell('I3')->getValue())->toBe(2)  // Poin Tambahan
            ->and((int) $sheet->getCell('J3')->getValue())->toBe(6); // Total Poin
    });

    it('only includes projects whose project_date is inside the range', function () {
        perfSeedProductionPoint('In Range Event', 'Employee A', '2026-01-15', 3, 0, ['In Range Task']);
        perfSeedProductionPoint('Out Of Range Event', 'Employee B', '2026-03-20', 9, 0, ['Out Of Range Task']);

        $sheet = perfRenderSheet('2026-01-01', '2026-01-31');

        $allText = collect($sheet->toArray())->flatten()->filter()->implode(' | ');

        expect($allText)->toContain('In Range Event')
            ->and($allText)->toContain('In Range Task')
            ->and($allText)->not->toContain('Out Of Range Event')
            ->and($allText)->not->toContain('Out Of Range Task');
    });

    it('renders the PIC and feedback columns for the project', function () {
        [$project] = perfSeedProductionPoint('Product Launch', 'Dewi Lestari', '2026-01-12', 2, 0, ['Task A']);

        $pic = Employee::factory()->create(['name' => 'Yanuar', 'nickname' => 'Yanuar']);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pic->id]);
        ProjectFeedback::create([
            'project_id' => $project->id,
            'pic_id' => $pic->id,
            'feedback' => 'Well done',
            'points' => [],
            'submitted_at' => now(),
            'submitted_by' => $pic->id,
        ]);

        $sheet = perfRenderSheet('2026-01-01', '2026-01-31');

        expect((string) $sheet->getCell('C3')->getValue())->toContain('Yanuar');   // PM / PIC
        expect((string) $sheet->getCell('K3')->getValue())->toContain('Well done'); // feedback
    });

    it('renders without throwing when the period is not supplied', function () {
        // Guards the "Undefined variable $startDate" crash: an old queued job (or any caller)
        // rendering the template without the period must still produce the sheet.
        $html = view('hrd::new-export-performance-report', ['points' => []])->render();

        expect($html)->toContain('LAPORAN PERFORMA KARYAWAN');
    });
});
