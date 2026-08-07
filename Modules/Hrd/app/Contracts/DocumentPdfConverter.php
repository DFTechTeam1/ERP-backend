<?php

namespace Modules\Hrd\Contracts;

use Modules\Hrd\Exceptions\PdfConversionFailed;

interface DocumentPdfConverter
{
    /**
     * Convert a .docx file into a PDF and return the absolute path of the generated PDF.
     *
     * The PDF is written alongside the source file. Implementations must not mutate or delete
     * the source document.
     *
     * @param  string  $absoluteDocxPath  Absolute filesystem path to the source .docx
     * @return string Absolute filesystem path to the generated .pdf
     *
     * @throws PdfConversionFailed
     */
    public function toPdf(string $absoluteDocxPath): string;
}
