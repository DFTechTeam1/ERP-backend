<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

/**
 * POST /api/production/ai-search/download - the frontend posts a full file URL and the route
 * fetches it and streams it back as a browser download (attachment disposition).
 */
beforeEach(function () {
    actingAs(User::factory()->create());
});

it('fetches the file and returns it as a download with the URL filename', function () {
    Http::fake([
        'https://tunnel.dfactory.pro/*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
    ]);

    $url = 'https://tunnel.dfactory.pro/storage/nas/image-processed/192.168.100.101/PREVIEW/2024_11_24_Singgih%20Jacinda_TESTCARD.png';

    $response = $this->postJson('/api/production/ai-search/download', ['url' => $url]);

    $response->assertStatus(200);

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')
        ->and($disposition)->toContain('2024_11_24_Singgih Jacinda_TESTCARD.png')  // %20 decoded
        ->and($response->headers->get('content-type'))->toContain('image/png')
        ->and($response->getContent())->toBe('PNG-BYTES');
});

it('rejects a URL hosted outside the company domain (SSRF guard)', function () {
    Http::fake();

    $this->postJson('/api/production/ai-search/download', ['url' => 'https://evil.example.com/secret.png'])
        ->assertStatus(403);

    Http::assertNothingSent();
});

it('rejects an internal/loopback host', function () {
    Http::fake();

    $this->postJson('/api/production/ai-search/download', ['url' => 'http://127.0.0.1/admin'])
        ->assertStatus(403);

    Http::assertNothingSent();
});

it('rejects a missing or invalid url', function () {
    $this->postJson('/api/production/ai-search/download', [])->assertStatus(422);
    $this->postJson('/api/production/ai-search/download', ['url' => 'not-a-url'])->assertStatus(422);
});

it('returns 404 when the remote file cannot be fetched', function () {
    Http::fake([
        'https://tunnel.dfactory.pro/*' => Http::response('', 404),
    ]);

    $this->postJson('/api/production/ai-search/download', [
        'url' => 'https://tunnel.dfactory.pro/storage/nas/missing.png',
    ])->assertStatus(404);
});

it('rejects a file larger than the 50 MB image cap', function () {
    Http::fake([
        'https://tunnel.dfactory.pro/*' => Http::response('x', 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) (51 * 1024 * 1024), // 21 MB
        ]),
    ]);

    $this->postJson('/api/production/ai-search/download', [
        'url' => 'https://tunnel.dfactory.pro/storage/nas/huge.png',
    ])->assertStatus(413);
});
