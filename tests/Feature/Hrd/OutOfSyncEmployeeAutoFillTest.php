<?php

use App\Enums\Employee\OutOfSyncStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Models\Setting;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\GreatdayCompany;
use Modules\Hrd\Models\GreatdayEmploymentStatus;
use Modules\Hrd\Models\GreatdayNationality;
use Modules\Hrd\Models\GreatdayTimezone;
use Modules\Hrd\Models\OutOfSyncEmployee;
use Modules\Hrd\Services\EmployeeService;

/**
 * One entry shaped exactly like the Greatday /employees response.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function greatdayAutoFillEntry(array $override = []): array
{
    return array_merge([
        'empId' => 'DO250015',
        'empNo' => 'DF049',
        'firstName' => 'Ilham',
        'middleName' => 'Meru',
        'lastName' => 'Gumilang',
        // Greatday puts the full name in nickName and the short name in userName
        'nickName' => 'Ilham Meru Gumilang',
        'userName' => 'Ilham',
        'identityNo' => '3573042405960004',
        'gender' => 1,
        'birthDate' => '1996-05-24T00:00:00.000Z',
        'birthPlace' => 'Malang',
        'email' => 'gumilang@dfactory.pro',
        'posCode' => 'POSFLLDEV',
        'posNameEn' => 'Full Stack Developer',
        'employmentStatus' => 'Karyawan Tetap',
        'employmentStatusCode' => 'PERMANENT',
        'startDate' => '2024-03-25T00:00:00.000Z',
        'endDate' => null,
        'companyId' => 35532,
        'address' => 'Jl. Bandulan VI, No 788',
        'phone' => '+626285795327357',
        'jobStatus' => '5WD',
        'worklocationCode' => 'KCP',
        'costCode' => 'DFV',
        'gradeCode' => 'STF',
        'bankCode' => 'CIMB',
        'bankAccount' => '763685137400',
        'bankAccountName' => 'Ilham Meru Gumilang',
        'orgUnit' => 'IT',
        'employmentStartDate' => '2024-03-25T00:00:00.000Z',
    ], $override);
}

/**
 * @param  array<int, array<string, mixed>>  $entries
 */
function fakeGreatdayAutoFillList(array $entries): void
{
    Http::fake(function ($request) use ($entries) {
        if (str_contains($request->url(), '/employees')) {
            $page = $request['page'] ?? 1;

            return Http::response(['data' => $page == 1 ? $entries : []], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });
}

beforeEach(function () {
    // The container exports CACHE_STORE=redis and phpunit.xml's force="true" override does
    // not win here, so the shared dev Redis is live during tests. getSettingByKey() caches
    // the WHOLE settings table forever, so a test that writes a Setting would otherwise
    // replace the dev cache with the test database's — pinning it at runtime keeps this
    // test's settings in-process.
    config(['cache.default' => 'array']);

    $this->actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);
    EmploymentStatus::factory()->create(['is_terminal' => 1]);
});

it('stores the Greatday personal and bank fields so the sync dialog can auto-fill them', function () {
    fakeGreatdayAutoFillList([greatdayAutoFillEntry()]);

    app(EmployeeService::class)->getOutOfSyncEmployees();

    $this->assertDatabaseHas('out_of_sync_employees', [
        'greatday_employee_id' => 'DO250015',
        'nickname' => 'Ilham',
        'id_number' => '3573042405960004',
        'gender' => 1,
        'birth_date' => '1996-05-24',
        'birth_place' => 'Malang',
        'grade_code' => 'STF',
        'bank_code' => 'CIMB',
        'bank_account' => '763685137400',
        'bank_account_name' => 'Ilham Meru Gumilang',
    ]);
});

it('refreshes an existing staging row instead of leaving it stale', function () {
    OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO250015', 'employee_id' => 'DF049', 'first_name' => 'Ilham',
        'email' => 'gumilang@dfactory.pro', 'position_code' => 'POSFLLDEV', 'position_name' => 'Dev',
        'employment_status' => 'Karyawan Tetap', 'employment_status_code' => 'PERMANENT',
        'company_id' => 35532, 'status' => OutOfSyncStatus::OutOfSync,
    ]);

    fakeGreatdayAutoFillList([greatdayAutoFillEntry(['gradeCode' => 'MGR'])]);

    app(EmployeeService::class)->getOutOfSyncEmployees();

    expect(OutOfSyncEmployee::count())->toBe(1);
    $this->assertDatabaseHas('out_of_sync_employees', [
        'greatday_employee_id' => 'DO250015',
        'grade_code' => 'MGR',
        'id_number' => '3573042405960004',
    ]);
});

it('never blanks an existing column when Greatday sends nothing for it', function () {
    OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO250015', 'employee_id' => 'DF049', 'first_name' => 'Ilham',
        'email' => 'gumilang@dfactory.pro', 'position_code' => 'POSFLLDEV', 'position_name' => 'Dev',
        'employment_status' => 'Karyawan Tetap', 'employment_status_code' => 'PERMANENT',
        'company_id' => 35532, 'address' => 'Jl. Bandulan VI, No 788', 'id_number' => '3573042405960004',
        'status' => OutOfSyncStatus::OutOfSync,
    ]);

    // Greatday commonly returns '' rather than omitting the key
    fakeGreatdayAutoFillList([greatdayAutoFillEntry(['address' => '', 'identityNo' => ''])]);

    app(EmployeeService::class)->getOutOfSyncEmployees();

    $this->assertDatabaseHas('out_of_sync_employees', [
        'greatday_employee_id' => 'DO250015',
        'address' => 'Jl. Bandulan VI, No 788',
        'id_number' => '3573042405960004',
    ]);
});

it('lists staged employees with their own values plus the office defaults', function () {
    $division = DivisionBackup::create(['name' => 'IT', 'greatday_position_id' => 10]);
    PositionBackup::create([
        'name' => 'Full Stack Developer', 'division_id' => $division->id,
        'greatday_code' => 'POSFLLDEV', 'greatday_position_id' => 37,
    ]);
    GreatdayCompany::create(['name' => 'DFactory Visual', 'code' => 'DFV', 'company_id' => 35532]);
    GreatdayEmploymentStatus::create(['name' => 'Karyawan Tetap', 'code' => 'PERMANENT', 'need_employment_date' => 'N']);
    GreatdayTimezone::create(['name' => 'Jakarta', 'timezone_id' => 7, 'gmt_ref_hour' => '07', 'gmt_ref_minute' => '00', 'gmt_plus_min' => '+']);
    GreatdayNationality::create(['name' => 'Indonesia', 'code' => 'ID']);
    $supervisor = Employee::factory()->create();

    Setting::create([
        'key' => 'employee_variable',
        'code' => 'employee_variable',
        'value' => json_encode([
            'nationality' => 'ID',
            'religion' => 'ISLAM',
            'marital_status' => '0',
            'timezone_id' => 7,
            'birth_place' => 'Surabaya',
            'employment_status' => 'PERMANENT',
            'shift_pattern' => 'SP01',
            'supervisor' => $supervisor->uid,
        ]),
    ]);
    Cache::forget('setting');

    fakeGreatdayAutoFillList([greatdayAutoFillEntry()]);
    app(EmployeeService::class)->getOutOfSyncEmployees();

    $result = app(EmployeeService::class)->listOutOfSyncEmployees();

    expect($result['error'])->toBeFalse();
    expect($result['data']['totalData'])->toBe(1);

    $row = $result['data']['paginated'][0];

    // the employee's own Greatday values
    // userName, not nickName (which holds the full name) and not the assembled full name
    expect($row['nickname'])->toBe('Ilham');
    expect($row['name'])->toBe('Ilham Meru Gumilang');
    expect($row['id_number'])->toBe('3573042405960004');
    expect($row['gender'])->toBe('1');
    expect($row['birth_place'])->toBe('Malang');
    expect($row['address'])->toBe('Jl. Bandulan VI, No 788');
    expect($row['grade_code'])->toBe('STF');
    expect($row['employment_status_code'])->toBe('PERMANENT');
    expect($row['work_location_code'])->toBe('KCP');
    expect($row['bank_name'])->toBe('CIMB');
    expect($row['bank_account_number'])->toBe('763685137400');
    expect($row['bank_account_holder_name'])->toBe('Ilham Meru Gumilang');
    expect($row['company_code'])->toBe('DFV');
    expect($row['department'])->toBe('IT');
    expect($row['position_uid_database'])->not->toBeEmpty();

    // date-picker formats — the component only round-trips "Y, F d"
    expect($row['birth_day'])->toBe('1996, May 24');
    expect($row['defaut_format_join_date'])->toBe('2024, March 25');
    expect($row['join_date'])->toBe('25 March 2024');

    // office-wide defaults for what Greatday never sends
    expect($row['default_data']['nationality'])->toBe('ID');
    expect($row['default_data']['religion'])->toBe('ISLAM');
    expect($row['default_data']['timezone_id'])->toBe(7);
    expect($row['default_data']['timezone_data']['name'])->toBe('(GMT+07:00) Jakarta');
    expect($row['default_data']['nationality_data']['nationality_code'])->toBe('ID');
    expect($row['default_data']['employment_status_format']['employment_status_code'])->toBe('PERMANENT');
    expect($row['default_data']['supervisor_data']['uid'])->toBe($supervisor->uid);
});

it('omits supervisor_data when the configured supervisor no longer exists', function () {
    Setting::create([
        'key' => 'employee_variable',
        'code' => 'employee_variable',
        'value' => json_encode([
            'supervisor' => 'a-uid-that-does-not-exist',
            'manager' => 'another-missing-uid',
        ]),
    ]);
    Cache::forget('setting');

    fakeGreatdayAutoFillList([greatdayAutoFillEntry()]);
    app(EmployeeService::class)->getOutOfSyncEmployees();

    $default = app(EmployeeService::class)->listOutOfSyncEmployees()['data']['paginated'][0]['default_data'];

    // The form pre-selects only when the option travels with the uid — otherwise the
    // lazy-loaded picker would render the raw uid instead of a name.
    expect($default)->not->toHaveKey('supervisor_data');
    expect($default)->not->toHaveKey('manager_data');
});

it('parses the date-picker format without dropping the year', function () {
    $service = app(EmployeeService::class);
    $parse = new ReflectionMethod($service, 'parseFormDate');

    // Carbon::parse('1996, May 24') silently resolves to the CURRENT year
    expect($parse->invoke($service, '1996, May 24'))->toBe('1996-05-24');
    expect($parse->invoke($service, '2024, March 05'))->toBe('2024-03-05');
    expect($parse->invoke($service, '2024, March 5'))->toBe('2024-03-05');

    // the read-only display copies still parse
    expect($parse->invoke($service, '25 March 2024'))->toBe('2024-03-25');
    expect($parse->invoke($service, '2024-03-25'))->toBe('2024-03-25');
});
