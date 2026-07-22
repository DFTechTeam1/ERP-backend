<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Exceptions\PdfConversionFailed;
use Modules\Hrd\Services\Pdf\GotenbergPdfConverter;

/**
 * Unit coverage for the docx -> PDF converter. Gotenberg itself is never contacted: the HTTP
 * client is faked, so these assert the request shape and file handling only.
 */
function makeTempDocx(): string
{
    $path = sys_get_temp_dir().'/gotenberg_'.uniqid().'.docx';
    file_put_contents($path, 'fake-docx-bytes');

    return $path;
}

it('posts the docx to the libreoffice endpoint and writes the returned pdf', function () {
    Http::fake([
        '*/forms/libreoffice/convert' => Http::response('%PDF-1.4 generated', 200),
    ]);

    $docx = makeTempDocx();
    $converter = new GotenbergPdfConverter('http://gotenberg:3000');

    $pdf = $converter->toPdf($docx);

    expect($pdf)->toEndWith('.pdf')
        ->and(file_get_contents($pdf))->toBe('%PDF-1.4 generated');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/forms/libreoffice/convert'));

    @unlink($docx);
    @unlink($pdf);
});

it('throws when the source document is missing', function () {
    $converter = new GotenbergPdfConverter('http://gotenberg:3000');

    expect(fn () => $converter->toPdf('/does/not/exist.docx'))
        ->toThrow(PdfConversionFailed::class);
});

it('throws when the conversion service returns an error', function () {
    Http::fake([
        '*/forms/libreoffice/convert' => Http::response('boom', 500),
    ]);

    $docx = makeTempDocx();
    $converter = new GotenbergPdfConverter('http://gotenberg:3000');

    expect(fn () => $converter->toPdf($docx))->toThrow(PdfConversionFailed::class);

    @unlink($docx);
});
