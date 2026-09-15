<?php

use App\Models\User;
use App\Services\UserService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Queue;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Services\EmployeeService;
use Modules\Hrd\Services\GreatdayService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * createEmployeeV2() mirrors the erp-backend-node POST /api/v2/hrd/employees endpoint: it resolves
 * the reference codes/uids, creates the employee (with the raw greatday_* attributes), optionally
 * invites the person as an ERP user, and optionally registers them on Greatday.
 *
 * UserService and GreatdayService are mocked where those side effects are exercised, so the tests
 * stay off the wire while still asserting the orchestration.
 */
function ev2Service(): EmployeeService
{
    return app(EmployeeService::class);
}

/**
 * @return array<string, mixed>
 */
function ev2Payload(string $positionUid, string $supervisorUid, array $override = []): array
{
    return array_merge([
        'email' => 'newhire_'.uniqid().'@dfactory.pro',
        'first_name' => 'Ilham',
        'middle_name' => 'Meru',
        'last_name' => 'Gumilang',
        'nickname' => 'Ilham Meru',
        'id_number' => (string) random_int(1000000000000000, 9999999999999999),
        'nationality' => 'ID',
        'gender' => '1',
        'birth_day' => '1995-05-24',
        'birth_place' => 'Surabaya',
        'religion' => 'islam',
        'marital_status' => 'single',
        'timezone_id' => '7',
        'mobile_phone' => '+6285795327357',
        'address' => 'Jl. Test No. 1',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'Ilham Meru Gumilang',
        'employee_no' => 'DF'.random_int(1000, 9999),
        'join_date' => '2024-03-25',
        'company_id' => 'CMP1',
        'position' => $positionUid,
        'job_grade' => 'Staff',
        'cost_center' => 'CC1',
        'employment_status' => 'PERM',
        'work_location' => 'HQ',
        'supervisor' => $supervisorUid,
        'manager' => $supervisorUid,
        'shift_pattern' => 'NORMAL',
        'job_status' => 'active',
        'register_on_greatday' => 0,
        'invite_on_erp' => 0,
    ], $override);
}

function ev2GreatdayResponse(array $body): ClientResponse
{
    return new ClientResponse(new GuzzleResponse(200, [], json_encode($body)));
}

beforeEach(function () {
    Queue::fake();

    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail(), 'sanctum');

    EmploymentStatus::create(['code' => 'PERM', 'name' => 'Permanent']);
    $this->position = PositionBackup::factory()->create();
    $this->supervisor = Employee::factory()->create();
});

describe('createEmployeeV2 (service)', function () {
    it('creates the employee with the mapped fields', function () {
        $payload = ev2Payload($this->position->uid, $this->supervisor->uid);

        $response = ev2Service()->createEmployeeV2($payload);

        expect($response['error'])->toBeFalse();

        assertDatabaseHas('employees', [
            'employee_id' => $payload['employee_no'],
            'email' => $payload['email'],
            'name' => 'Ilham Meru Gumilang',       // first + middle + last
            'nickname' => 'ilhammeru',              // spaces stripped, lowercased
            'phone' => '6285795327357',             // leading + removed
            'religion' => 'islam',                  // hardcoded
            'martial_status' => 'single',           // hardcoded
            'gender' => 'male',                     // '1' -> male
            'position_id' => $this->position->id,
            'boss_id' => $this->supervisor->id,
            'level_staff' => 'staff',
            'basic_salary' => 0,
            'salary_type' => 1,
            'is_phone_verified' => 0,
            'greatday_nationality' => 'ID',
            'greatday_company' => 'CMP1',
            'greatday_timezone' => '7',
            'greatday_job_status' => 'active',
        ]);

        $employee = Employee::where('employee_id', $payload['employee_no'])->firstOrFail();
        expect((int) $employee->employment_status_id)->toBe(EmploymentStatus::where('code', 'PERM')->value('id'))
            ->and(json_decode($employee->bank_detail, true)[0]['account_number'])->toBe('1234567890');
    });

    it('rejects a duplicate email or employee number', function () {
        $existing = Employee::factory()->create(['employee_id' => 'DFDUP1']);

        $byEmail = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, $this->supervisor->uid, [
            'email' => $existing->email,
        ]));
        $byNo = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, $this->supervisor->uid, [
            'employee_no' => 'DFDUP1',
        ]));

        expect($byEmail['error'])->toBeTrue()
            ->and($byEmail['message'])->toContain('already exists')
            ->and($byNo['error'])->toBeTrue()
            ->and($byNo['message'])->toContain('already exists');
    });

    it('rejects an invalid position', function () {
        $response = ev2Service()->createEmployeeV2(ev2Payload('non-existent-uid', $this->supervisor->uid));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Invalid position');
    });

    it('rejects an invalid supervisor', function () {
        $response = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, 'non-existent-uid'));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Invalid supervisor');
    });

    it('rejects an invalid employment status code', function () {
        $response = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, $this->supervisor->uid, [
            'employment_status' => 'NOPE',
        ]));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Invalid employment status');
    });

    it('invites the employee to the ERP when invite_on_erp is set', function () {
        $this->mock(UserService::class, function ($mock) {
            $mock->shouldReceive('mainServiceStoreUser')
                ->once()
                ->withArgs(fn ($p) => $p['is_external_user'] === 0
                    && $p['role_id'] === 5
                    && ! empty($p['email'])
                    && ! empty($p['employee_id']))
                ->andReturn(new User);
        });

        $payload = ev2Payload($this->position->uid, $this->supervisor->uid, [
            'invite_on_erp' => 1,
            'role' => 5,
        ]);

        $response = ev2Service()->createEmployeeV2($payload);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('employees', ['employee_id' => $payload['employee_no']]);
    });

    it('does not invite to the ERP when invite_on_erp is 0', function () {
        $this->mock(UserService::class, function ($mock) {
            $mock->shouldReceive('mainServiceStoreUser')->never();
        });

        $response = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, $this->supervisor->uid, [
            'invite_on_erp' => 0,
        ]));

        expect($response['error'])->toBeFalse();
    });

    it('registers the employee on Greatday and stores the returned id', function () {
        $employeeNo = 'DFGD'.random_int(1000, 9999);

        $this->mock(GreatdayService::class, function ($mock) use ($employeeNo) {
            $mock->shouldReceive('addEmployee')
                ->once()
                ->andReturn(ev2GreatdayResponse([['success' => true]]));
            $mock->shouldReceive('authedPost')
                ->with('/employees', Mockery::type('array'))
                ->andReturn(
                    ev2GreatdayResponse(['data' => [['empNo' => $employeeNo, 'empId' => 'GD999']]]),
                    ev2GreatdayResponse(['data' => []]),
                );
        });

        $response = ev2Service()->createEmployeeV2(ev2Payload($this->position->uid, $this->supervisor->uid, [
            'register_on_greatday' => 1,
            'employee_no' => $employeeNo,
        ]));

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('employees', [
            'employee_id' => $employeeNo,
            'greatday_emp_id' => 'GD999',
        ]);
    });
});

describe('createEmployeeV2 (e2e)', function () {
    it('creates an employee over the v2 API', function () {
        $payload = ev2Payload($this->position->uid, $this->supervisor->uid);

        $response = $this->postJson('/api/v2/hrd/employees', $payload);

        $response->assertStatus(201);
        assertDatabaseHas('employees', [
            'employee_id' => $payload['employee_no'],
            'email' => $payload['email'],
            'name' => 'Ilham Meru Gumilang',
        ]);
    });

    it('validates the payload (422 on missing required fields)', function () {
        $this->postJson('/api/v2/hrd/employees', [])->assertStatus(422);
    });

    it('rejects unauthenticated access', function () {
        auth()->forgetGuards();

        $this->postJson('/api/v2/hrd/employees', ev2Payload($this->position->uid, $this->supervisor->uid))
            ->assertUnauthorized();
    });
});
