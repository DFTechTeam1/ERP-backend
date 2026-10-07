<?php

use App\Data\Finance\ProjectCost\ProjectItemData;
use App\Enums\Production\ProjectStatus;
use App\Exports\ProjectRewardReportExport;
use App\Exports\RewardReportSheet;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Company\Models\ProjectClass;
use Modules\Finance\Services\ProjectCostService;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectQuotation;
use PhpOffice\PhpSpreadsheet\IOFactory;

use function Pest\Laravel\actingAs;

/**
 * ProjectCostService and the ProjectCostController endpoints under
 * GET /api/production/project-costs/*.
 *
 * The service builds the finance "project cost" dashboard from projects filtered by the request
 * `year` / `month` / `event_class`, enriched with their employee rewards (cost) and the linked
 * deal's quotation (fix price). Every project is dated in the current year (2026) so the default
 * "all year" filter (Carbon::now()) matches it.
 *
 * `year` is required by the service: getProjects() runs projectConditions() which reads
 * request('year'); the tests always supply it.
 */
function pcostService(): ProjectCostService
{
    return app(ProjectCostService::class);
}

/**
 * Set the dashboard request filters for a service-level call.
 *
 * @param  array<string, mixed>  $filters
 */
function pcostFilters(array $filters = ['year' => '2026']): void
{
    request()->merge($filters);
}

/**
 * Create a project dated in 2026 with a class, a deal and its final quotation (the fix price).
 */
function pcostProject(array $attrs = [], float $fixPrice = 0, bool $fullyPaid = false, ?ProjectClass $class = null): Project
{
    $class ??= ProjectClass::factory()->create(['name' => 'Class A', 'color' => '#FF0000']);

    $deal = ProjectDeal::factory()->create(['is_fully_paid' => $fullyPaid]);
    ProjectQuotation::factory()->create([
        'project_deal_id' => $deal->id,
        'is_final' => 1,
        'fix_price' => $fixPrice,
    ]);

    return Project::factory()->create(array_merge([
        'project_deal_id' => $deal->id,
        'project_class_id' => $class->id,
        'project_date' => '2026-06-15',
        'status' => ProjectStatus::OnGoing->value,
    ], $attrs));
}

/**
 * Attach one employee reward (the project cost) to a project.
 */
function pcostReward(Project $project, string $name, int $totalPoint, float $totalReward): Employee
{
    $employee = Employee::factory()->withUser()->create(['name' => $name, 'avatar' => 'a.png']);

    EmployeeReward::create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'employee_point_project_id' => null,
        'base_reward' => $totalReward,
        'total_point' => $totalPoint,
        'point' => $totalPoint,
        'additional_point' => 0,
        'total_reward' => $totalReward,
        'project_class_name' => 'Class A',
        'role' => 'production',
    ]);

    return $employee;
}

/**
 * Attach a person in charge (PIC) to a project.
 */
function pcostPic(Project $project, string $name): Employee
{
    $employee = Employee::factory()->create(['name' => $name]);
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $employee->id]);

    return $employee;
}

beforeEach(function () {
    // getProjects() caches under a single key per filter (PROJECTS:COSTS:Y::2026...). The container
    // test run uses the redis cache (shared with dev), so that key would leak the first test's
    // result into every later test. Force the isolated in-memory array store so each test starts
    // with a clean cache and never touches redis.
    config(['cache.default' => 'array']);
});

describe('getDashboardSummary', function () {
    it('aggregates cost, fix price, gross profit and averages (service)', function () {
        pcostFilters();
        $a = pcostProject(fixPrice: 100000000, fullyPaid: true);
        $b = pcostProject(fixPrice: 60000000, fullyPaid: false);
        pcostReward($a, 'Budi', 5, 250000);
        pcostReward($b, 'Sri', 3, 150000);

        $response = pcostService()->getDashboardSummary();

        expect($response['error'])->toBeFalse();
        $data = $response['data'];
        expect($data['total_projects'])->toBe(2)
            ->and((float) $data['total_cost'])->toBe(400000.0)          // 250k + 150k
            ->and((float) $data['total_employee_reward'])->toBe(400000.0)
            ->and((float) $data['total_fix_price'])->toBe(160000000.0)  // 100M + 60M
            ->and((float) $data['total_gross_profit'])->toBe(159600000.0) // 160M - 400k
            ->and((float) $data['average_cost'])->toBe(200000.0)        // 400k / 2
            ->and((float) $data['provisional_count'])->toBe(1.0)        // one not fully paid
            ->and($data['currency'])->toBe('IDR');
    });

    it('applies the project name search to the summary aggregates (service)', function () {
        pcostFilters(['year' => '2026', 'search' => 'Alpha']);
        $alpha = pcostProject(['name' => 'Alpha Wedding'], fixPrice: 100000000);
        $beta = pcostProject(['name' => 'Beta Gala'], fixPrice: 50000000);
        pcostReward($alpha, 'A', 1, 300000);
        pcostReward($beta, 'B', 1, 150000);

        $response = pcostService()->getDashboardSummary();

        expect($response['error'])->toBeFalse();
        $data = $response['data'];
        // only the matching "Alpha Wedding" project feeds the totals
        expect($data['total_projects'])->toBe(1)
            ->and((float) $data['total_cost'])->toBe(300000.0)
            ->and((float) $data['total_fix_price'])->toBe(100000000.0);
    });

    it('returns an error response when no project matches (division by zero, characterisation)', function () {
        pcostFilters();

        $response = pcostService()->getDashboardSummary();

        // count() is 0 -> round($totalCost / 0) throws DivisionByZeroError -> caught -> error
        expect($response['error'])->toBeTrue();
    });

    it('returns the summary via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject(fixPrice: 100000000);
        pcostReward($project, 'Budi', 5, 250000);

        $this->getJson('/api/production/project-costs/summary?year=2026')
            ->assertStatus(201)
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.currency', 'IDR');
    });
});

describe('getCostTrend', function () {
    it('groups total cost and reward by month (service)', function () {
        pcostFilters();
        $jun = pcostProject(['project_date' => '2026-06-10']);
        $jul = pcostProject(['project_date' => '2026-07-12']);
        pcostReward($jun, 'Budi', 4, 200000);
        pcostReward($jul, 'Sri', 2, 100000);

        $response = pcostService()->getCostTrend();

        expect($response['error'])->toBeFalse()
            ->and($response['data'])->toHaveCount(2);

        $byMonth = collect($response['data'])->keyBy('month');
        expect((float) $byMonth['Jun 2026']->total_cost)->toBe(200000.0)
            ->and((float) $byMonth['Jul 2026']->employee_reward)->toBe(100000.0);
    });

    it('returns the trend via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject(['project_date' => '2026-06-10']);
        pcostReward($project, 'Budi', 4, 200000);

        $this->getJson('/api/production/project-costs/cost-trend?year=2026')
            ->assertStatus(201)
            ->assertJsonPath('data.0.month', 'Jun 2026');
    });
});

describe('getCostByClass', function () {
    it('groups total cost by event class (service)', function () {
        pcostFilters();
        $classA = ProjectClass::factory()->create(['name' => 'A', 'color' => '#111']);
        $classB = ProjectClass::factory()->create(['name' => 'B', 'color' => '#222']);
        $a1 = pcostProject(class: $classA);
        $a2 = pcostProject(class: $classA);
        $b1 = pcostProject(class: $classB);
        pcostReward($a1, 'W1', 1, 100000);
        pcostReward($a2, 'W2', 1, 50000);
        pcostReward($b1, 'W3', 1, 30000);

        $response = pcostService()->getCostByClass();

        expect($response['error'])->toBeFalse()
            ->and($response['data'])->toHaveCount(2);

        $byClass = collect($response['data'])->keyBy('event_class');
        expect((float) $byClass['A']->total_cost)->toBe(150000.0)   // 100k + 50k
            ->and((float) $byClass['B']->total_cost)->toBe(30000.0);
    });

    it('returns the cost-by-class via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $class = ProjectClass::factory()->create(['name' => 'A', 'color' => '#111']);
        $project = pcostProject(class: $class);
        pcostReward($project, 'W1', 1, 100000);

        $this->getJson('/api/production/project-costs/cost-by-class?year=2026')
            ->assertStatus(201)
            ->assertJsonPath('data.0.event_class', 'A')
            ->assertJsonPath('data.0.total_cost', 100000);
    });
});

describe('getCostComposition', function () {
    it('returns the employee reward composition total (service)', function () {
        pcostFilters();
        $a = pcostProject();
        $b = pcostProject();
        pcostReward($a, 'Budi', 4, 200000);
        pcostReward($b, 'Sri', 2, 100000);

        $response = pcostService()->getCostComposition();

        expect($response['error'])->toBeFalse()
            ->and($response['data'])->toHaveCount(1);
        expect($response['data'][0]['key'])->toBe('employee_reward')
            ->and((float) $response['data'][0]['total'])->toBe(300000.0);
    });

    it('returns the composition via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject();
        pcostReward($project, 'Budi', 4, 200000);

        $this->getJson('/api/production/project-costs/cost-composition?year=2026')
            ->assertStatus(201)
            ->assertJsonPath('data.0.key', 'employee_reward')
            ->assertJsonPath('data.0.total', 200000);
    });
});

describe('getDashboardLatest', function () {
    it('returns up to five recent projects, skipping the first row (service)', function () {
        pcostFilters();
        // getProjectPagination(5, 1) => skip(1)->take(5), ordered by project_date DESC
        for ($i = 1; $i <= 6; $i++) {
            $project = pcostProject(['project_date' => "2026-06-0{$i}"]);
            pcostReward($project, "W{$i}", 1, 10000 * $i);
        }

        $response = pcostService()->getDashboardLatest();

        expect($response['error'])->toBeFalse()
            ->and($response['data'])->toHaveCount(5); // 6 rows, skip 1, take 5

        expect($response['data'][0])->toBeInstanceOf(ProjectItemData::class)
            ->and($response['data'][0]->currency)->toBe('IDR');
    });

    it('returns the latest widget via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        // two projects: getProjectPagination(5, 1) skips one, leaving a single row
        pcostReward(pcostProject(['project_date' => '2026-06-01']), 'Budi', 1, 10000);
        pcostReward(pcostProject(['project_date' => '2026-06-02']), 'Sri', 1, 20000);

        $this->getJson('/api/production/project-costs/latest?year=2026')
            ->assertStatus(201)
            ->assertJsonPath('message', 'Success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.currency', 'IDR');
    });
});

describe('getDashboard', function () {
    it('returns the paginated listing with the total count (service)', function () {
        pcostFilters(['year' => '2026', 'itemsPerPage' => 10, 'page' => 1]);
        $p1 = pcostProject(fixPrice: 100000000);
        $p2 = pcostProject(fixPrice: 50000000);
        $p3 = pcostProject(fixPrice: 25000000);
        pcostReward($p1, 'A', 1, 100000);
        pcostPic($p1, 'Yanuar');

        $response = pcostService()->getDashboard();

        expect($response['error'])->toBeFalse();
        $data = $response['data'];
        expect($data['totalData'])->toBe(3)
            ->and($data['paginated'])->toHaveCount(3)
            ->and($data['paginated'][0]['currency'])->toBe('IDR');
    });

    it('returns the paginated dashboard via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject(fixPrice: 100000000);
        pcostReward($project, 'Budi', 1, 100000);

        $this->getJson('/api/production/project-costs?year=2026&itemsPerPage=10&page=1')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1);
    });

    it('filters the listing and total by a project name search (service)', function () {
        pcostFilters(['year' => '2026', 'itemsPerPage' => 10, 'page' => 1, 'search' => 'Alpha']);
        pcostProject(['name' => 'Alpha Wedding']);
        pcostProject(['name' => 'Beta Gala']);

        $response = pcostService()->getDashboard();

        expect($response['error'])->toBeFalse();
        $data = $response['data'];
        expect($data['totalData'])->toBe(1)                       // total honours the search filter
            ->and($data['paginated'])->toHaveCount(1)
            ->and($data['paginated'][0]['name'])->toBe('Alpha Wedding');
    });

    it('filters the listing by search via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        pcostProject(['name' => 'Alpha Wedding']);
        pcostProject(['name' => 'Beta Gala']);

        $this->getJson('/api/production/project-costs?year=2026&itemsPerPage=10&page=1&search=Alpha')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.name', 'Alpha Wedding');
    });
});

describe('detailProjectCost', function () {
    it('builds the full cost detail for a project (service)', function () {
        $project = pcostProject(['name' => 'Grand Wedding', 'venue' => 'Grand Hall'], fixPrice: 120000000);
        pcostReward($project, 'Budi Santoso', 5, 250000);
        pcostReward($project, 'Sri Wahyuni', 3, 150000);
        pcostPic($project, 'Yanuar');

        $response = pcostService()->detailProjectCost($project->uid);

        expect($response['error'])->toBeFalse();
        $data = $response['data'];
        expect($data['uid'])->toBe($project->uid)
            ->and($data['name'])->toBe('Grand Wedding')
            ->and($data['venue'])->toBe('Grand Hall')
            ->and($data['pic'])->toBe('Yanuar')
            ->and((float) $data['fix_price'])->toBe(120000000.0)
            ->and((float) $data['total_cost'])->toBe(400000.0)         // 250k + 150k
            ->and((float) $data['employee_rewards']['total'])->toBe(400000.0)
            ->and($data['employee_rewards']['items'])->toHaveCount(2)
            ->and($data['currency'])->toBe('IDR')
            ->and($data['payment_status'])->toBe('partial');           // fix price > 0, nothing paid
    });

    it('returns the detail via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject(['name' => 'Gala Night'], fixPrice: 120000000);
        pcostReward($project, 'Budi', 5, 250000);

        $this->getJson("/api/production/project-costs/{$project->uid}")
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Gala Night')
            ->assertJsonPath('data.employee_rewards.items.0.name', 'Budi');
    });
});

/**
 * Attach a reward for a SPECIFIC employee to a project (unlike pcostReward which spawns a fresh
 * employee each call). Used by the export tests, where the same employee must appear on several
 * projects so the disbursement sheet can aggregate across them.
 */
function pcostRewardFor(Project $project, Employee $employee, string $role, int $totalPoint, float $totalReward): EmployeeReward
{
    return EmployeeReward::create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'employee_point_project_id' => null,
        'base_reward' => $totalReward,
        'total_point' => $totalPoint,
        'point' => $totalPoint,
        'additional_point' => 0,
        'total_reward' => $totalReward,
        'project_class_name' => $project->projectClass->name ?? null,
        'role' => $role,
    ]);
}

describe('export', function () {
    it('builds a three-sheet workbook: summary, detail and per-employee disbursement (service)', function () {
        pcostFilters(['year' => '2026']);

        $class = ProjectClass::factory()->create(['name' => 'Gold', 'color' => '#D4AF37']);
        $p1 = pcostProject(['name' => 'Wedding Gala', 'project_date' => '2026-03-10'], class: $class);
        $p2 = pcostProject(['name' => 'Corporate Show', 'project_date' => '2026-04-12'], class: $class);

        $andre = Employee::factory()->create(['name' => 'Andre', 'employee_id' => 'DF-001']);
        $bella = Employee::factory()->create(['name' => 'Bella', 'employee_id' => 'DF-002']);

        // Andre is rewarded on BOTH projects (as production); Bella only on p1 (as PM).
        pcostRewardFor($p1, $andre, 'production', 3, 100000);
        pcostRewardFor($p1, $bella, 'pm', 0, 50000);
        pcostRewardFor($p2, $andre, 'production', 2, 80000);

        Excel::fake();

        pcostService()->export();

        Excel::assertDownloaded('reward_report_2026.xlsx', function (ProjectRewardReportExport $export) {
            $sheets = $export->sheets();
            expect($sheets)->toHaveCount(3);

            [$summary, $detail, $disbursement] = $sheets;

            expect($summary->title())->toBe('Summary')
                ->and($detail->title())->toBe('Reward Detail')
                ->and($disbursement->title())->toBe('Disbursement');

            // --- Summary: one row per project + a grand total row, with total reward AND total cost ---
            expect($summary->headings())->toContain('Total Reward (IDR)')
                ->and($summary->headings())->toContain('Total Cost (IDR)');
            $summaryRows = collect($summary->array());
            $gala = $summaryRows->firstWhere(1, 'Wedding Gala');
            $show = $summaryRows->firstWhere(1, 'Corporate Show');
            expect((float) $gala[6])->toBe(150000.0)  // total reward: 100k Andre + 50k Bella
                ->and((float) $gala[7])->toBe(150000.0)  // total cost (= reward for now)
                ->and((int) $gala[5])->toBe(2)          // two rewarded employees
                ->and((float) $show[6])->toBe(80000.0)
                ->and((float) $show[7])->toBe(80000.0);
            $summaryTotal = $summaryRows->firstWhere(5, 'TOTAL');
            expect((float) $summaryTotal[6])->toBe(230000.0)   // grand total reward
                ->and((float) $summaryTotal[7])->toBe(230000.0); // grand total cost

            // money columns are real numbers displayed via a grouped number format (not strings,
            // which the value binder would coerce back to the wrong number)
            expect($summary->columnFormats())->toHaveKey('G')
                ->and($summary->columnFormats())->toHaveKey('H')
                ->and($detail->columnFormats())->toHaveKey('I')
                ->and($detail->columnFormats()['I'])->toContain('#,##0')
                ->and($disbursement->columnFormats())->toHaveKey('F');

            // only the Summary header is filterable (auto filter registered via an AfterSheet event);
            // it spans the header + the two project rows (A1:H3), not the grand total row
            expect($summary->registerEvents())->not->toBeEmpty()
                ->and($detail->registerEvents())->toBeEmpty()
                ->and($disbursement->registerEvents())->toBeEmpty();

            // --- Reward Detail: one row per reward line; reward stays numeric ---
            $detailRows = collect($detail->array());
            expect($detailRows)->toHaveCount(3);
            $bellaRow = $detailRows->firstWhere(1, 'Bella');
            expect($bellaRow[3])->toBe('Project Manager')   // role label mapping
                ->and($bellaRow[8])->toBe(50000.0);           // numeric reward (grouped at render)
            // Andre appears on two projects
            expect($detailRows->where(1, 'Andre'))->toHaveCount(2);

            // --- Disbursement: aggregated per employee + total row ---
            $disbursementRows = collect($disbursement->array());
            $andreOut = $disbursementRows->firstWhere(1, 'DF-001');
            $bellaOut = $disbursementRows->firstWhere(1, 'DF-002');
            expect((int) $andreOut[4])->toBe(2)              // two projects
                ->and($andreOut[5])->toBe(180000.0)          // 100k + 80k
                ->and((int) $bellaOut[4])->toBe(1)
                ->and($bellaOut[5])->toBe(50000.0);
            expect($disbursementRows->firstWhere(4, 'TOTAL')[5])->toBe(230000.0);

            return true;
        });
    });

    it('shows numeric zero (not a blank cell) in the summary for a project with no reward records (service)', function () {
        pcostFilters(['year' => '2026']);
        // a project with a deal/quotation but NO employee rewards attached
        pcostProject(['name' => 'No Reward Event', 'project_date' => '2026-07-01']);

        Excel::fake();

        pcostService()->export();

        Excel::assertDownloaded('reward_report_2026.xlsx', function (ProjectRewardReportExport $export) {
            $summaryRows = collect($export->sheets()[0]->array());
            $row = $summaryRows->firstWhere(1, 'No Reward Event');

            expect($row)->not->toBeNull()
                ->and($row[5])->toBe(0)       // Rewarded Employees
                ->and($row[6])->toBe(0.0)     // Total Reward -> numeric 0, never blank
                ->and($row[7])->toBe(0.0);    // Total Cost   -> numeric 0, never blank

            return true;
        });
    });

    it('scopes the report to the requested month and names the file accordingly (service)', function () {
        pcostFilters(['year' => '2026', 'month' => '3']);

        $inRange = pcostProject(['name' => 'March Event', 'project_date' => '2026-03-20']);
        $outRange = pcostProject(['name' => 'April Event', 'project_date' => '2026-04-20']);
        pcostReward($inRange, 'In Range', 1, 70000);
        pcostReward($outRange, 'Out Range', 1, 99000);

        Excel::fake();

        pcostService()->export();

        Excel::assertDownloaded('reward_report_2026_03.xlsx', function (ProjectRewardReportExport $export) {
            $summaryRows = collect($export->sheets()[0]->array());

            expect($summaryRows->firstWhere(1, 'March Event'))->not->toBeNull()
                ->and($summaryRows->firstWhere(1, 'April Event'))->toBeNull();
            // only the in-range project feeds the grand totals (reward and cost)
            $total = $summaryRows->firstWhere(5, 'TOTAL');
            expect((float) $total[6])->toBe(70000.0)
                ->and((float) $total[7])->toBe(70000.0);

            return true;
        });
    });

    it('streams the reward report download via the endpoint (e2e)', function () {
        actingAs(User::factory()->create());
        $project = pcostProject(['name' => 'Annual Gala', 'project_date' => '2026-05-01']);
        pcostReward($project, 'Budi', 4, 200000);

        Excel::fake();

        $this->get('/api/production/project-costs/export?year=2026')
            ->assertStatus(200);

        Excel::assertDownloaded('reward_report_2026.xlsx');
    });

    it('renders grouped money, a filterable header, and writes 0 (not blank) into a real xlsx', function () {
        // Render a sheet straight to xlsx (no fake) so the number format, auto filter and the way a
        // 0 is written can be read back exactly as a spreadsheet application would see them.
        $sheet = new RewardReportSheet(
            title: 'Summary',
            headings: ['No', 'Project', 'Total Reward (IDR)'],
            rows: [[1, 'Big Event', 200000.0], [2, 'No Reward Event', 0.0]],
            columnFormats: ['C' => '[$-421]#,##0'],
            autoFilter: 'A1:C3',
        );

        $binary = Excel::raw($sheet, Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'reward_report_').'.xlsx';
        file_put_contents($path, $binary);
        $loaded = IOFactory::load($path)->getActiveSheet();

        // the reward cell is a real number (200000), displayed grouped as "200.000"
        expect((float) $loaded->getCell('C2')->getValue())->toBe(200000.0)
            ->and($loaded->getStyle('C2')->getNumberFormat()->getFormatCode())->toBe('[$-421]#,##0')
            ->and($loaded->getAutoFilter()->getRange())->toBe('A1:C3');

        // a 0 amount is written as numeric 0, NOT skipped into a blank cell
        expect($loaded->getCell('C3')->getValue())->not->toBeNull()
            ->and((float) $loaded->getCell('C3')->getValue())->toBe(0.0);
    });
});
