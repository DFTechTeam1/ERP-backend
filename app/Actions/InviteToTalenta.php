<?php

namespace App\Actions;

use Exception;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteToTalenta
{
    use AsAction;

    private $talentaService;

    private $repo;

    public function handle($data, $employee)
    {
        $this->talentaService->setUrl('store_employee');
        $response = $this->talentaService->setUrlParams($this->talentaService->buildEmployeePayload($data));

        // Throw error when it failed
        if ($response['message'] != 'success') {
            throw new Exception(__('notification.failedSaveToTalent'));
        }

        // update talenta user ID
        $this->talentaService->setUrl('detail_employee');
        $this->talentaService->setUrlParams(['email' => $data['email']]);
        $currentTalentaEmployee = $this->talentaService->makeRequest();

        $talentaUserId = $currentTalentaEmployee['data']['employees'][0]['user_id'];

        $this->repo->update([
            'talenta_user_id' => $talentaUserId,
        ], $employee->uid);
    }
}
