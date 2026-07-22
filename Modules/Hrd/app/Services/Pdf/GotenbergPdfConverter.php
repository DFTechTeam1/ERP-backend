<?php

namespace Modules\Hrd\Services\Pdf;

use Illuminate\Support\Facades\Http;
use Modules\Hrd\Contracts\DocumentPdfConverter;
use Modules\Hrd\Exceptions\PdfConversionFailed;

/**
 * Converts .docx documents to PDF through a Gotenberg service (which wraps LibreOffice headless
 * behind an HTTP API). Keeping the heavy office toolchain in a sidecar container keeps the Laravel
 * image lean and the conversion concurrency-safe.
 */
class GotenbergPdfConverter implements DocumentPdfConverter
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 120,
    ) {}

    public function toPdf(string $absoluteDocxPath): string
    {
        if (! is_file($absoluteDocxPath)) {
            throw new PdfConversionFailed('Source document not found for PDF conversion.');
        }

        $endpoint = rtrim($this->baseUrl, '/').'/forms/libreoffice/convert';

        $response = Http::timeout($this->timeout)
            ->attach('files', file_get_contents($absoluteDocxPath), basename($absoluteDocxPath))
            ->post($endpoint);

        if (! $response->successful()) {
            throw new PdfConversionFailed('PDF conversion service responded with status '.$response->status().'.');
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $absoluteDocxPath);

        if ($pdfPath === $absoluteDocxPath) {
            $pdfPath = $absoluteDocxPath.'.pdf';
        }

        file_put_contents($pdfPath, $response->body());

        return $pdfPath;
    }
}
