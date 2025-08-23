<?php

use App\Actions\Project\WriteDurationTaskHistory;
use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskHistoryType;
use App\Enums\Production\TaskStatus;
use App\Enums\Production\WorkType;
use App\Exports\ProjectDealSummary;
use App\Http\Controllers\Api\InteractiveController;
use App\Http\Controllers\LandingPageController;
use App\Jobs\ProjectDealSummaryJob;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Models\User;
use App\Notifications\DummyNotification;
use App\Services\EncryptionService;
use App\Services\GeneralService;
use App\Services\PusherNotification;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Company\Models\City;
use Modules\Company\Models\PositionBackup;
use Modules\Finance\Http\Controllers\Api\InvoiceController;
use Modules\Finance\Jobs\InvoiceDue;
use Modules\Finance\Jobs\ProjectHasBeenFinal as JobsProjectHasBeenFinal;
use Modules\Finance\Jobs\RequestInvoiceChangeJob;
use Modules\Finance\Jobs\TransactionCreatedJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Notifications\ApproveInvoiceChangesNotification;
use Modules\Finance\Notifications\InvoiceDueCheckNotification;
use Modules\Finance\Notifications\ProjectHasBeenFinal;
use Modules\Finance\Notifications\RequestInvoiceChangesNotification;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Hrd\Models\Employee;
use Modules\Production\Http\Controllers\Api\ProjectController;
use Modules\Production\Http\Controllers\Api\QuotationController;
use Modules\Production\Jobs\ProjectDealCanceledJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectQuotation;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskDurationHistory;
use Modules\Production\Models\ProjectTaskHold;
use Modules\Production\Models\ProjectTaskPicLog;

Route::get('/', [LandingPageController::class, 'index']);

Route::get('interactive/download', [InteractiveController::class, 'download']);

Route::get('send-email-testing', function () {
    if (App::environment(['production'])) {
        return view('onlyForLocal');
    }

    $user = User::latest()->first();
    $password = generateRandomPassword(length: 20);

    $service = new \App\Services\EncryptionService;
    $encrypt = $service->encrypt($user->email, env('SALT_KEY'));

    Notification::send($user, new \Modules\Hrd\Notifications\UserEmailActivation($user, $encrypt, $password));
});

Route::get('quotation/{quotationId}/{token}', function (string $quotationId, string $token) {
});

Route::get('barcode', function () {
    //    $data = \Modules\Inventory\Models\CustomInventory::select('barcode', 'build_series', 'id')
    //        ->get();
    //    $data = collect($data)->map(function ($item) {
    //        $item['barcode_path'] = asset('storage/'. $item->barcode);
    //
    //        return $item;
    //    })->toArray();
    //
    //    return view('barcode', compact('data'));
    $colorRed = [255, 0, 0];
    $barcode = (new \Picqer\Barcode\Types\TypeCode128)->getBarcode('https://google.com');
    $renderer = new \Picqer\Barcode\Renderers\PngRenderer;
    $renderer->setForegroundColor($colorRed);
    file_put_contents('barcode.png', $renderer->render($barcode, 300, 80));

    $image = asset('barcode.png');

    return view('testing_barcode', compact('image'));
});

Route::get('generate-official-email', [\App\Http\Controllers\Api\TestingController::class, 'generateOfficialEmail']);

Route::get('trigger', function () {
    $pusher = new \App\Services\PusherNotification;

    $pusher->send('channel-interactive-new', 'notification-event', ['message' => 'Hello']);
});

Route::get('ilham', function () {
    $endDate = date('Y-m-d', strtotime('+2 days'));

    $tasks = ProjectTask::selectRaw('id,uid,project_id,name')
        ->with([
            'pics:id,project_task_id,employee_id',
            'pics.employee:id,nickname,email,line_id',
            'project:id,name',
        ])
        ->whereIn(
            'status',
            [
                TaskStatus::WaitingApproval->value,
                TaskStatus::OnProgress->value,
                TaskStatus::Revise->value,
            ]
        )
        ->where('end_date', $endDate)
        ->get();

    $outputData = [];
    foreach ($tasks as $task) {
        foreach ($task->pics as $employee) {
            $outputData[] = [
                'employee' => $employee,
                'task' => $task,
            ];
        }
    }

    UpcomingDeadlineTaskJob::dispatch($outputData);
});

Route::get('login', function () {
    return view('auth.login');
})->name('login');


Route::get('quotations/download/{quotationId}/{type}', [QuotationController::class, 'quotation']);

// route to download invoice after
Route::get('/invoices/download/{type}', [InvoiceController::class, 'downloadInvoiceBasedOnType'])->name('invoice.download.type');
// Route::get('invoices/download', [InvoiceController::class, 'downloadInvoice'])->name('invoice.download')
//     ->middleware('signed');
Route::get('invoices/general/download', [InvoiceController::class, 'downloadGeneralInvoice'])->name('invoice.general.download')
    ->middleware('signed');

Route::get('/notification-preview', function () {
    return Auth::user();
})->middleware('auth:sanctum');

Route::get('dummy-send-email', function () {
    $user = Employee::where('email', 'gumilang.dev@gmail.com')->first();

    if ($user) {
        $user->notify(new DummyNotification);

        return 'Email sent successfully!';
    }

    return 'User not found.';
});

Route::get('checkdummy', function () {
    DB::beginTransaction();
    try {
        $city = City::first();
        $marketing = Employee::latest()->first();

        $project = Project::factory()->create([
            'status' => ProjectStatus::Completed->value,
            'name' => "DUMMY DATA PROJECT",
            'city_id' => $city->id,
            'state_id' => $city->state_id,
            'country_id' => $city->country_id,
            'marketing_id' => $marketing->id
        ]);

        $projectBoards = ProjectBoard::create([
            'project_id' => $project->id,
            'name' => '3D modeller',
            'sort' => 0,
            'base_board_id' => 1
        ]);
    
        $pm = PositionBackup::where('name', 'Project Manager')->first();
        $personAsPm = Employee::whereRaw("name like '%rudhi%'")->first();
    
        $pmStaffs = Employee::where('boss_id', $personAsPm->id)->get();
    
        if ($pm) {
            // Do something with the project manager
            ProjectPersonInCharge::create([
                'project_id' => $project->id,
                'pic_id' => $personAsPm->id
            ]);
        }
    
        $tasks = ProjectTask::factory()->count(3)->create([
            'project_id' => $project->id,
            'is_approved' => 1,
            'is_modeler_task' => 0,
            'project_board_id' => $projectBoards->id
        ]);
    
        foreach ($tasks as $key => $task) {
            if ($key == 0) {
                // create task
                // create data for project_task_duration_histories with:
                // 1. task_full_duration is 3 hour (in second)
                // 2. task_holded_duration is 33 minutes (in second)
                // 3. task_revised_duration is 0 (in second)
                // 4. task_approval_duration is 45 minutes (in second)
                // 5. task_actual_duration is 3 hour - 33 minutes (in second)
                ProjectTaskDurationHistory::create([
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'pic_id' => $personAsPm->id,
                    'employee_id' => $pmStaffs[0]->id,
                    'task_type' => TaskHistoryType::SingleAssignee->value,
                    'task_full_duration' => 10800,
                    'task_holded_duration' => 1980,
                    'task_revised_duration' => 0,
                    'task_approval_duration' => 2700,
                    'task_actual_duration' => 10800 - 1980,
                    'total_task_holded' => 2,
                    'total_task_revised' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
    
            if ($key == 1) {
                // create task
                // create data for project_task_duration_histories with:
                // 1. task_full_duration is 32 hour (in second)
                // 2. task_holded_duration is 24 minutes (in second)
                // 3. task_revised_duration is 0 (in second)
                // 4. task_approval_duration is 50 minutes (in second)
                // 5. task_actual_duration is 32 hour - 24 minutes (in second)
                ProjectTaskDurationHistory::create([
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'pic_id' => $personAsPm->id,
                    'employee_id' => $pmStaffs[1]->id,
                    'task_type' => TaskHistoryType::SingleAssignee->value,
                    'task_full_duration' => 115200,
                    'task_holded_duration' => 1440,
                    'task_revised_duration' => 0,
                    'task_approval_duration' => 3000,
                    'task_actual_duration' => 115200 - 1440,
                    'total_task_holded' => 2,
                    'total_task_revised' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            if ($key == 2) {
                // create task
                // create data for project_task_duration_histories with:
                // 1. task_full_duration is 14 hour (in second)
                // 2. task_holded_duration is 0 minutes (in second)
                // 3. task_revised_duration is 4 hour (in second)
                // 4. task_approval_duration is 50 minutes (in second)
                // 5. task_actual_duration is 14 hour - 4 hour (in second)
                ProjectTaskDurationHistory::create([
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'pic_id' => $personAsPm->id,
                    'employee_id' => $pmStaffs[0]->id,
                    'task_type' => TaskHistoryType::SingleAssignee->value,
                    'task_full_duration' => 50400,
                    'task_holded_duration' => 0,
                    'task_revised_duration' => 14400,
                    'task_approval_duration' => 3000,
                    'task_actual_duration' => 50400 - 14400,
                    'total_task_holded' => 2,
                    'total_task_revised' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }

        DB::commit();
        
        return $project;
    } catch (\Throwable $th) {
        DB::rollBack();
        return errorMessage($th);
    }
});

Route::get('pusher-check', function() {
    (new \App\Services\PusherNotification)->send(
        channel: "my-channel-42",
        event: "handle-export-import-notification",
        payload: [
            'type' => 'exportImportSuccess',
            'message' => 'Success import data'
        ],
        compressedValue: true
    );
});

Route::get('expired', function () {
    return view('errors.expired');
});

Route::get('i/p', function (Request $request) {
    $title = $request->type == 'deal' ? 'Event Changes Approved' : 'Invoice Approved';
    $message = $request->type == 'deal' ? 'Event changes have been successfully approved. The updates are now live in the system.' : 'Your invoice changes have been successfully approved. The updates are now live in the system.';

    return view('invoices.approved', compact('title', 'message'));
})->name('invoices.approved');
Route::get('i/r', function (Request $request) {
    $title = $request->type == 'deal' ? 'Event Changes Rejected' : 'Invoice Rejected';
    $message = $request->type == 'deal' ? 'Changes request rejected.' : 'Invoice successfully changed. Change request rejected.';

    return view('invoices.rejected', compact('title', 'message'));
})->name('invoices.rejected');