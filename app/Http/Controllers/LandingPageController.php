<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
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
        return Employee::with(['tasks', 'projects'])
            ->where('id', 17)->first();
        return view('landing');
    }
}
