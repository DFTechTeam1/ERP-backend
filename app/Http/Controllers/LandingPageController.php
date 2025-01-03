<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Production\Services\ProjectRepositoryGroup;
use Vinkla\Hashids\Facades\Hashids;

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
        return view('landing');
    }
}
