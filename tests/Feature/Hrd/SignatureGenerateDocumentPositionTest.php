<?php

use App\Data\Hrd\Signature\GenerateDocumentData;
use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;
use Modules\Hrd\Services\SignatureService;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * employee_position_name is a relation-based placeholder ('relation' => 'position:id,name') in
 * config('signature.available_replacer_column'). getDocumentColumnsReplacer() used to drop every
 * relation entry, so ${employee_position_name} was never replaced when generating a document.
 * These tests cover both the resolver (relations are now surfaced) and the end-to-end generation
 * (the placeholder is replaced with the employee's actual position name).
 */
function sgpService(): SignatureService
{
    return app(SignatureService::class);
}

function sgpWriteTemplate(string $absolutePath, string $body): void
{
    @mkdir(dirname($absolutePath), 0775, true);
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($body);
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
}

it('surfaces relation-based placeholders from the document mapping', function () {
    $file = new MasterDocumentFile;
    $file->placeholder_mapping = ['name', 'join_date', 'end_date', 'employee_position_name'];

    $master = new MasterDocument;
    $master->setRelation('activeDocument', $file);

    $result = Closure::bind(
        fn ($document) => $this->getDocumentColumnsReplacer($document),
        sgpService(),
        SignatureService::class
    )($master);

    $relation = collect($result['relations'])->firstWhere('from', 'employee_position_name');

    expect($relation)->not->toBeNull()
        ->and($relation['with'])->toBe('position:id,name')
        ->and($relation['path'])->toBe('position.name')
        ->and($result['keys'])->toContain('name', 'join_date', 'end_date');
});

it('replaces the employee_position_name placeholder with the employee position on generation', function () {
    Queue::fake();

    $creator = User::factory()->create();
    $position = PositionBackup::factory()->create(['name' => 'Senior 3D Modeller']);
    $employee = Employee::factory()->create([
        'position_id' => $position->id,
        'employee_id' => 'DFPOS'.random_int(1000, 9999),
        'name' => 'Ilham Meru Gumilang',
        'join_date' => '2024-03-25',
    ]);

    $type = DocumentType::create([
        'name' => 'Work Contract '.uniqid(),
        'code' => 'WC'.random_int(10000, 99999),
        'retention' => 12,
        'default_number_of_signers' => 1,
        'status' => 1,
        'created_by' => $creator->id,
    ]);

    $templateRel = 'documents/templates/test_'.uniqid().'.docx';
    sgpWriteTemplate(
        storage_path('app/public/'.$templateRel),
        'Name: ${name}, Position: ${employee_position_name}, Join: ${join_date}'
    );

    $master = MasterDocument::create([
        'name' => 'Work Contract Template',
        'document_type_id' => $type->id,
    ]);
    MasterDocumentFile::create([
        'master_document_id' => $master->id,
        'path' => $templateRel,
        'file_type' => 'docx',
        'placeholder_mapping' => ['name', 'employee_position_name', 'join_date'],
        'version' => 1,
        'status' => DocumentFileStatus::Active,
        'created_by' => $creator->id,
    ]);

    $response = sgpService()->generateDocument(
        new GenerateDocumentData(employee_id: $employee->employee_id, version_label: 'v1'),
        $master->uid
    );

    expect($response['error'])->toBeFalse();

    $employeeDocument = EmployeeDocument::where('employee_id', $employee->id)->latest('id')->firstOrFail();
    $outputPath = storage_path('app/public/'.$employeeDocument->document_path);

    expect(file_exists($outputPath))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open($outputPath);
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($documentXml)->toContain('Senior 3D Modeller')          // position name resolved
        ->and($documentXml)->not->toContain('employee_position_name') // placeholder is gone
        ->and($documentXml)->toContain('Ilham Meru Gumilang');       // direct placeholder still works

    // cleanup generated artifacts
    @unlink($outputPath);
    @unlink(storage_path('app/public/'.$templateRel));
});
