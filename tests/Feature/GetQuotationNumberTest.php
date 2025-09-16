<?php

use App\Services\GeneralService;
use Modules\Production\Repository\ProjectQuotationRepository;

it('Generate quotation when data is empty', function () {
    $service = createProjectService();

    // mockup general service
    $general = Mockery::mock(GeneralService::class);
    $general->shouldReceive('getSettingByKey')
        ->withAnyArgs()
        ->andReturn('DF');

    $response = $service->getQuotationNumber();

    expect($response)->toHaveKey('data');
    expect($response['data']['number'])->toBe('DF0001');
});

it('Generate quotation number when we have 100 data', function () {
    $output = collect([
        [
            'quotation_id' => 'DF0100',
        ],
    ]);

    $mock = Mockery::mock(ProjectQuotationRepository::class);
    $mock->shouldReceive('list')
        ->withAnyArgs()
        ->andReturn($output);

    $general = Mockery::mock(GeneralService::class);
    $general->shouldReceive('getSettingByKey')
        ->withAnyArgs()
        ->andReturn('DF');

    $service = createProjectService(
        projectQuotationRepo: $mock
    );

    $response = $service->getQuotationNumber();

    expect($response)->toHaveKey('data');
    expect($response['data']['number'])->toBe('DF0101');
});

it('Generate quotation number when we have 1000 data', function () {
    $output = collect([
        [
            'quotation_id' => 'DF01100',
        ],
    ]);

    $mock = Mockery::mock(ProjectQuotationRepository::class);
    $mock->shouldReceive('list')
        ->withAnyArgs()
        ->andReturn($output);

    $general = Mockery::mock(GeneralService::class);
    $general->shouldReceive('getSettingByKey')
        ->withAnyArgs()
        ->andReturn('DF');

    $service = createProjectService(
        projectQuotationRepo: $mock
    );

    $response = $service->getQuotationNumber();

    expect($response)->toHaveKey('data');
    expect($response['data']['number'])->toBe('DF01101');
});
