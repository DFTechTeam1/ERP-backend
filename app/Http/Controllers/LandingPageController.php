<?php

namespace App\Http\Controllers;

use App\Enums\System\BaseRole;
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
        $users = \App\Models\User::role([BaseRole::Entertainment->value, BaseRole::ProjectManagerEntertainment->value])
                ->with([
                    'employee' => function ($query) {
                        $query->selectRaw('id,name')
                            ->with([
                                'songTasks' => function ($taskQuery) {
                                    $taskQuery->selectRaw('id,project_song_list_id,employee_id,project_id,status')
                                        ->where('project_id', 255)
                                        ->with('song:id,name,uid');
                                }
                            ]);
                    }
                ])
                ->get();
        return $users;
        return view('landing');
    }
}
