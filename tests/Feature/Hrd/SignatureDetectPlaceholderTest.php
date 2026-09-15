<?php

use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Services\SignatureService;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * detectPlaceholder() reports which template placeholders the system can fill, driven by
 * config('signature.available_replacer_column'). A work contract letter needs join_date and
 * end_date, so both must be recognised (present in 'availables', never flagged in 'missing').
 *
 * These tests build a real .docx with PhpWord (so PhpWord's TemplateProcessor parses genuine
 * variables) and run the service end to end. Any unknown placeholder must still be flagged.
 */
function sdpService(): SignatureService
{
    return app(SignatureService::class);
}

function sdpDocumentType(): DocumentType
{
    return DocumentType::create([
        'name' => 'Work Contract '.uniqid(),
        'code' => 'WC'.random_int(10000, 99999),
        'retention' => 12,
        'default_number_of_signers' => 1,
        'status' => 1,
        'created_by' => User::factory()->create()->id,
    ]);
}

function sdpDocxWith(string $body): UploadedFile
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($body);

    $path = storage_path('app/public/signature/tmp/src_'.uniqid().'.docx');
    @mkdir(dirname($path), 0775, true);
    IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    return new UploadedFile(
        $path,
        'contract.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );
}

afterEach(function () {
    $dir = storage_path('app/public/signature/tmp');
    if (is_dir($dir)) {
        array_map('unlink', glob($dir.'/*') ?: []);
    }
});

it('maps join_date and end_date to employee columns in the config', function () {
    $config = config('signature.available_replacer_column');

    expect($config)->toHaveKeys(['join_date', 'end_date'])
        ->and($config['join_date']['model'])->toBe(Employee::class)
        ->and($config['join_date']['column'])->toBe('join_date')
        ->and($config['end_date']['model'])->toBe(Employee::class)
        ->and($config['end_date']['column'])->toBe('end_date');
});

it('recognises join_date and end_date placeholders in a work contract letter', function () {
    $type = sdpDocumentType();
    $file = sdpDocxWith('Name: ${name}, Join: ${join_date}, End: ${end_date}, Sign: ${employeeSignature}');

    $response = sdpService()->detectPlaceholder(new DetectPlaceholderData($file, (string) $type->id));

    expect($response['error'])->toBeFalse();

    $data = $response['data'];

    expect($data['availables'])->toContain('join_date', 'end_date')
        ->and($data['variables'])->toContain('join_date', 'end_date')
        ->and($data['missing'])->not->toContain('join_date')
        ->and($data['missing'])->not->toContain('end_date');
});

it('still flags an unknown placeholder as missing while accepting join_date and end_date', function () {
    $type = sdpDocumentType();
    $file = sdpDocxWith('Join: ${join_date}, End: ${end_date}, Salary: ${salary}');

    $response = sdpService()->detectPlaceholder(new DetectPlaceholderData($file, (string) $type->id));

    expect($response['error'])->toBeFalse();

    $data = $response['data'];

    expect($data['missing'])->toContain('salary')
        ->and($data['missing'])->not->toContain('join_date')
        ->and($data['missing'])->not->toContain('end_date');
});
