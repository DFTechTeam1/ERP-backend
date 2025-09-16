<?php

use Modules\Company\Models\ExportImportResult;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);

    $this->authUser = $user;
});

it('Show pagination content of import export results', function () {
    $user = $this->authUser;

    $lists = ExportImportResult::factory()
        ->count(5)
        ->create([
            'user_id' => $user->id,
        ]);

    $route = route('api.company.inboxData').'?itemsPerPage=10&page=1';
    $response = getJson($route);

    $response->assertStatus(201);

    expect($response->json())->toHaveKeys(['message', 'data.paginated', 'data.totalData']);

    expect(count($response->json()['data']['paginated']))->toBe(5);
});
