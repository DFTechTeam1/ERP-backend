<?php

use App\Models\Mcp\McpAccessLog;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

test('it lists mcp access logs with pagination', function () {
    McpAccessLog::factory()->count(3)->create();

    $response = $this->getJson('/api/mcp-logs');

    $response->assertSuccessful();

    expect($response->json('data'))->toHaveKeys(['paginated', 'totalData']);
    expect($response->json('data.totalData'))->toBe(3);
    expect($response->json('data.paginated'))->toHaveCount(3);
});

test('it filters logs by success state', function () {
    McpAccessLog::factory()->success()->count(2)->create();
    McpAccessLog::factory()->failed()->count(1)->create();

    $response = $this->getJson('/api/mcp-logs?is_success=false');

    $response->assertSuccessful();
    expect($response->json('data.totalData'))->toBe(1);
});

test('it returns summary totals', function () {
    McpAccessLog::factory()->success()->count(4)->create();
    McpAccessLog::factory()->failed()->count(2)->create();

    $response = $this->getJson('/api/mcp-logs/summary');

    $response->assertSuccessful();
    expect($response->json('data.total'))->toBe(6);
    expect($response->json('data.success'))->toBe(4);
    expect($response->json('data.failed'))->toBe(2);
});

test('it returns usage grouped by period', function () {
    McpAccessLog::factory()->count(3)->create(['accessed_at' => now()]);

    $response = $this->getJson('/api/mcp-logs/usage?period=day');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.total'))->toBe(3);
});

test('it returns usage grouped by route', function () {
    McpAccessLog::factory()->count(2)->create(['route_uri' => 'mcp/finance/insight', 'method' => 'GET']);

    $response = $this->getJson('/api/mcp-logs/usage-by-route');

    $response->assertSuccessful();
    expect($response->json('data.0.route_uri'))->toBe('mcp/finance/insight');
    expect($response->json('data.0.total'))->toBe(2);
});

test('it shows a single log detail', function () {
    $log = McpAccessLog::factory()->create();

    $response = $this->getJson('/api/mcp-logs/'.$log->id);

    $response->assertSuccessful();
    expect($response->json('data.id'))->toBe($log->id);
});

test('it returns not found for missing log', function () {
    $response = $this->getJson('/api/mcp-logs/999999');

    $response->assertStatus(400);
});
