<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Hrd\Services\TalentaService;
use Modules\Production\Services\ProjectRepositoryGroup;

class LandingPageController extends Controller
{
    private $projectRepoGroup;

    public function __construct(
        ProjectRepositoryGroup $projectRepoGroup
    )
    {
        $this->projectRepoGroup = $projectRepoGroup;
    }

    public function index()
    {
        $service = new TalentaService();
        $service->setUrl(type: 'detail_employee');
        $service->setUrlParams(params: [
            'email' => 'email'
        ]);
        $response = $service->makeRequest();

        return $response;   
        if (isset($response['data'])) {
        }
        return view('landing');
    }
}
