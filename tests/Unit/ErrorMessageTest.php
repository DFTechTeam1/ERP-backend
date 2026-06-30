<?php

use App\Exceptions\SongHaveNoTask;
use Modules\Production\Exceptions\SongNotFound;

/**
 * errorMessage() drives every errorResponse() body. It must expose technical
 * detail in dev / staging while only ever leaking human-readable strings in
 * production. These are pure unit tests — no database needed.
 */
afterEach(function () {
    config(['app.env' => 'testing']);
});

it('returns the raw string for a direct call', function () {
    config(['app.env' => 'production']);

    expect(errorMessage('Failed to process transaction'))
        ->toBe('Failed to process transaction');
});

it('falls back when a direct call passes an empty string', function () {
    config(['app.env' => 'production']);

    expect(errorMessage(''))->toBe(__('global.failedProcessingData'));
});

it('exposes technical detail for a thrown error in dev / staging', function (string $env) {
    config(['app.env' => $env]);

    $exception = new RuntimeException('boom');

    $message = errorMessage($exception);

    expect($message)
        ->toContain('boom')
        ->toContain('at line')
        ->toContain('Check file');
})->with(['local', 'staging', 'testing']);

it('hides technical detail of a generic throwable in production', function () {
    config(['app.env' => 'production']);

    expect(errorMessage(new RuntimeException('SQLSTATE secret detail')))
        ->toBe(__('global.failedProcessingData'));
});

it('surfaces a domain exception message in production', function () {
    config(['app.env' => 'production']);

    expect(errorMessage(new SongHaveNoTask))
        ->toBe(__('notification.songHaveNoTask'))
        ->not->toContain('Check file');
});

it('treats App and Module exception namespaces as domain exceptions', function () {
    expect(isDomainException(new SongHaveNoTask))->toBeTrue();
    expect(isDomainException(new SongNotFound))->toBeTrue();
    expect(isDomainException(new RuntimeException('x')))->toBeFalse();
});
