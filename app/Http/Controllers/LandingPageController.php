<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Services\ProjectRepositoryGroup;

class LandingPageController extends Controller
{
    private $projectRepoGroup;

    private $employeePointService;

    private $reportService;

    private $employeeRepo;

    public function __construct(
        ProjectRepositoryGroup $projectRepoGroup,
        EmployeePointService $employeePointService,
        PerformanceReportService $reportService,
        EmployeeRepository $employeeRepo
    ) {
        $this->projectRepoGroup = $projectRepoGroup;

        $this->employeePointService = $employeePointService;

        $this->reportService = $reportService;

        $this->employeeRepo = $employeeRepo;
    }

    protected function getProjectData()
    {
        try {
            // code...
            $data = \Modules\Production\Models\Project::selectRaw('id,name,project_date,status')
                ->with([
                    'personInCharges:id,pic_id,project_id',
                ])
                ->get();

            $output = [];

            $data = \Illuminate\Support\Facades\DB::table('projects')
                ->leftJoin(table: 'project_classes', first: 'project_classes.project_id', operator: '=', second: 'projects.id')
                ->whereDate('projects.project_date', '>=', '2025-01-1')
                ->groupBy('p.project_class_id')
                ->dumpRawSql();
        } catch (\Throwable $th) {
            // throw $th;
            return errorResponse($th);
        }
    }

    public function index()
    {
        return view('landing');
    }

    /**
     * Show the login form for documentation access
     * 
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        // If user is already authenticated, redirect to intended page
        // if (Auth::guard('web')->check()) {
        //     return redirect()->intended('/');
        // }
        
        return view('auth.login');
    }

    /**
     * Handle login request for documentation access
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        
        if ($user && Hash::check($request->password, $user->password)) {
            Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password]); // Start web session
            return redirect('/scalar');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle logout request
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Log::info('Documentation logout', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email ?? 'unknown',
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('documentation.login')
            ->with('success', 'You have been logged out successfully.');
    }


    public function sendToNAS()
    {
        $filePath = public_path('images/user.png');
        $username = 'ilhamgumilang'; // Change this to NAS username
        $password = 'Ilham..123'; // Change this to NAS password

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://192.168.100.105:3500',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($filePath),
            ],
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            throw new \Exception('Upload failed: '.curl_error($curl));
        }

        curl_close($curl);

        echo json_encode($response);
    }
}
