<?php

use Illuminate\Support\Facades\Crypt;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;

it('returns latest and final quotation download links for a project deal', function () {
    $projectDeal = ProjectDeal::factory()->create([
        'name' => 'Deal With Quotations',
    ]);

    ProjectQuotation::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'quotation_id' => 'DF0001',
        'is_final' => 0,
    ]);

    $finalQuotation = ProjectQuotation::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'quotation_id' => 'DF0002',
        'is_final' => 1,
    ]);

    $service = createProjectDealService();

    $response = $service->getQuotationDownloadLink(
        projectDealUid: Crypt::encryptString($projectDeal->id)
    );

    expect($response['error'])->toBeFalse();

    $latest = $response['data']['latest_quotation'];
    expect($latest['quotation_id'])->toBe('DF0002');
    expect($latest['download_url'])->toContain('quotations/download/');
    expect($latest['download_url'])->toEndWith('/download');

    $final = $response['data']['final_quotation'];
    expect($final['quotation_id'])->toBe($finalQuotation->quotation_id);
    expect($final['download_url'])->toContain('quotations/download/');
});

it('returns final quotation as null when the deal has no final quotation', function () {
    $projectDeal = ProjectDeal::factory()->create();

    ProjectQuotation::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'quotation_id' => 'DF0003',
        'is_final' => 0,
    ]);

    $service = createProjectDealService();

    $response = $service->getQuotationDownloadLink(
        projectDealUid: Crypt::encryptString($projectDeal->id)
    );

    expect($response['error'])->toBeFalse();
    expect($response['data']['latest_quotation']['quotation_id'])->toBe('DF0003');
    expect($response['data']['final_quotation'])->toBeNull();
});

it('returns an error when the project deal has no quotation', function () {
    $projectDeal = ProjectDeal::factory()->create();

    $service = createProjectDealService();

    $response = $service->getQuotationDownloadLink(
        projectDealUid: Crypt::encryptString($projectDeal->id)
    );

    expect($response['error'])->toBeTrue();
    expect($response['message'])->toBe(__('notification.quotationNotFound'));
});

it('strips the hash from the quotation id before encrypting the download link', function () {
    $projectDeal = ProjectDeal::factory()->create();

    ProjectQuotation::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'quotation_id' => '#DF0004',
        'is_final' => 0,
    ]);

    $service = createProjectDealService();

    $response = $service->getQuotationDownloadLink(
        projectDealUid: Crypt::encryptString($projectDeal->id)
    );

    $url = $response['data']['latest_quotation']['download_url'];
    $encrypted = str_replace(['/download', url('quotations/download').'/'], '', $url);

    expect(Crypt::decryptString($encrypted))->toBe('DF0004');
});
