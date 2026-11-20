<?php

it('records handled requests and exposes them on /metrics', function () {
    // An unmatched request is still recorded: route label "{unmatched}", status 404.
    $this->get('/a-path-that-does-not-exist')->assertNotFound();

    $response = $this->get('/metrics');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    expect($response->getContent())
        ->toContain('laravel_http_requests_total')
        ->toContain('laravel_http_request_duration_seconds_bucket')
        ->toContain('status="404"');
});

it('hides /metrics from clients outside the private network', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('/metrics')
        ->assertNotFound();
});
