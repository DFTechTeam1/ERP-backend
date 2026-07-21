<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\GreatdayCompany;

use function Pest\Laravel\actingAs;

/**
 * @param  array<int, array<string, mixed>>  $employeesList
 */
function fakeGreatday(array $employeesList): void
{
    Http::fake(function ($request) use ($employeesList) {
        if (str_contains($request->url(), '/employees')) {
            // return the list on page 1 only, empty afterwards, so the paginated fetch terminates
            $page = $request['page'] ?? 1;

            return Http::response(['data' => $page == 1 ? $employeesList : []], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });
}

beforeEach(function () {
    actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);

    $division = DivisionBackup::create(['name' => 'Entertainment', 'greatday_position_id' => 10]);
    $this->position = PositionBackup::create(['name' => 'Visual Jockey', 'division_id' => $division->id, 'greatday_code' => 'POSX', 'greatday_position_id' => 100]);
});

it('reports already_linked when the employee has a greatday id', function () {
    $employee = Employee::factory()->create(['greatday_emp_id' => 'DO260077', 'position_id' => $this->position->id]);

    $response = $this->getJson(route('api.greatday.employeeLinkCheck', ['employeeUid' => $employee->uid]));

    $response->assertStatus(201);
    expect($response->json('data.already_linked'))->toBeTrue();
    expect($response->json('data.greatday_emp_id'))->toBe('DO260077');
});

it('reports a match (no form needed) when the person already exists in greatday', function () {
    $employee = Employee::factory()->create(['greatday_emp_id' => null, 'email' => 'match@x.test', 'position_id' => $this->position->id]);

    fakeGreatday([['empId' => 'DO500', 'empNo' => 'DFZ', 'email' => 'match@x.test']]);

    $response = $this->getJson(route('api.greatday.employeeLinkCheck', ['employeeUid' => $employee->uid]));

    $response->assertStatus(201);
    expect($response->json('data.needs_create'))->toBeFalse();
    expect($response->json('data.ready'))->toBeTrue();
    expect($response->json('data.match.greatday_emp_id'))->toBe('DO500');
});

it('reports needs_create with the missing fields when there is no match', function () {
    $employee = Employee::factory()->create(['greatday_emp_id' => null, 'position_id' => $this->position->id, 'greatday_nationality' => null, 'boss_id' => null]);

    fakeGreatday([]); // no greatday employees -> no match

    $response = $this->getJson(route('api.greatday.employeeLinkCheck', ['employeeUid' => $employee->uid]));

    $response->assertStatus(201);
    expect($response->json('data.needs_create'))->toBeTrue();
    $missing = collect($response->json('data.missing'))->keyBy('field');
    expect($missing->keys())->toContain('greatday_nationality')->toContain('boss_id');
    // position has a greatday_code here, so it must NOT be missing
    expect($missing->keys())->not->toContain('position');
    // fixable form fields are flagged fixable: true
    expect($missing['greatday_nationality']['fixable'])->toBeTrue();
    expect($missing['boss_id']['fixable'])->toBeTrue();
});

it('flags the position blocker as non-fixable with a hint', function () {
    $division = DivisionBackup::create(['name' => 'Prod', 'greatday_position_id' => 20]);
    $unlinkedPosition = PositionBackup::create(['name' => '3D Animator', 'division_id' => $division->id, 'greatday_code' => null, 'greatday_position_id' => null]);

    $employee = Employee::factory()->create([
        'greatday_emp_id' => null,
        'position_id' => $unlinkedPosition->id,
        'greatday_nationality' => 'ID', 'greatday_company' => '35532', 'greatday_job_grade' => 'STF',
        'greatday_cost_center' => 'DFV', 'greatday_employment_status' => 'PROBATION', 'greatday_work_location' => 'KCP',
        'greatday_religion' => 'ISL', 'greatday_timezone' => '30', 'greatday_shift_pattern' => 'GR',
        'boss_id' => Employee::factory()->create()->id,
    ]);

    fakeGreatday([]); // no match

    $missing = collect($this->getJson(route('api.greatday.employeeLinkCheck', ['employeeUid' => $employee->uid]))->json('data.missing'));

    // only the position is missing, and it's a non-fixable blocker with a hint
    expect($missing)->toHaveCount(1);
    $position = $missing->first();
    expect($position['field'])->toBe('position');
    expect($position['fixable'])->toBeFalse();
    expect($position['hint'])->toContain('3D Animator');
});

it('links by reusing an existing greatday record (match, no create)', function () {
    $employee = Employee::factory()->create(['greatday_emp_id' => null, 'email' => 'match@x.test', 'position_id' => $this->position->id]);

    fakeGreatday([['empId' => 'DO500', 'empNo' => 'DFZ', 'email' => 'match@x.test']]);

    $response = $this->postJson(route('api.greatday.employeeLink', ['employeeUid' => $employee->uid]), []);

    $response->assertStatus(201);
    expect($response->json('data.created'))->toBeFalse();
    expect($response->json('data.greatday_emp_id'))->toBe('DO500');

    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'greatday_emp_id' => 'DO500']);
});

it('rejects the link when required create fields are still missing', function () {
    $employee = Employee::factory()->create(['greatday_emp_id' => null, 'position_id' => $this->position->id, 'greatday_nationality' => null, 'boss_id' => null]);

    fakeGreatday([]); // no match -> needs create

    $response = $this->postJson(route('api.greatday.employeeLink', ['employeeUid' => $employee->uid]), []);

    $response->assertStatus(400);
    expect(collect($response->json('data.missing'))->pluck('field'))->toContain('greatday_nationality');
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'greatday_emp_id' => null]);
});

it('creates the employee in greatday, persists filled fields, and stores the new id', function () {
    $boss = Employee::factory()->create(['employee_id' => 'DF500']);
    GreatdayCompany::create(['code' => 'sfgo11677', 'company_id' => '35532', 'name' => 'DFactory Visual']);

    $employee = Employee::factory()->create([
        'greatday_emp_id' => null,
        'employee_id' => 'DF900',
        'email' => 'new@x.test',
        'position_id' => $this->position->id,
        'boss_id' => $boss->id,
        'greatday_nationality' => null, // will be supplied via the form
        'greatday_company' => 'sfgo11677', // company CODE, must resolve to numeric 35532
        'greatday_job_grade' => 'STF',
        'greatday_cost_center' => 'DFV',
        'greatday_employment_status' => 'PROBATION',
        'greatday_work_location' => 'KCP',
        'greatday_religion' => 'ISL',
        'greatday_timezone' => '30',
        'greatday_shift_pattern' => 'GR_SHIFT',
    ]);

    // no match before create; after the create POST, the new person appears in /employees
    $created = false;
    Http::fake(function ($request) use (&$created) {
        $url = $request->url();
        if (str_contains($url, '/employees/add') && $request->method() === 'POST') {
            $created = true;

            return Http::response([['empNo' => 'DF900', 'success' => true, 'message' => 'ok']], 200);
        }
        if (str_contains($url, '/employees')) {
            $page = $request['page'] ?? 1;
            $data = ($created && $page == 1) ? [['empId' => 'DO999', 'empNo' => 'DF900', 'email' => 'new@x.test']] : [];

            return Http::response(['data' => $data], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });

    $response = $this->postJson(route('api.greatday.employeeLink', ['employeeUid' => $employee->uid]), [
        'greatday_nationality' => 'ID',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.created'))->toBeTrue();
    expect($response->json('data.greatday_emp_id'))->toBe('DO999');

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'greatday_emp_id' => 'DO999',
        'greatday_nationality' => 'ID', // persisted from the form
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/employees/add') && $request->method() === 'POST'
        && $request->data()[0]['employeeNo'] === 'DF900'
        && $request->data()[0]['supervisor'] === 'DF500'
        && $request->data()[0]['companyId'] === 35532); // resolved from the code "sfgo11677"
});
