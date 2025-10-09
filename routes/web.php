<?php

use App\Enums\Production\TaskStatus;
use App\Http\Controllers\Api\InteractiveController;
use App\Http\Controllers\LandingPageController;
use App\Imports\SummaryInventoryReport;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Models\User;
use App\Notifications\DummyNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Finance\Http\Controllers\Api\InvoiceController;
use Modules\Finance\Http\Controllers\FinanceController;
use Modules\Finance\Models\Invoice;
use Modules\Hrd\Models\Employee;
use Modules\Production\Http\Controllers\Api\QuotationController;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\InteractiveProjectTaskRepository;

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

Route::get('quotation/{quotationId}/{token}', function (string $quotationId, string $token) {});

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

Route::get('check', function () {});

Route::get('inventory-check', function () {
    $service = app(\Modules\Inventory\Services\InventoryService::class);

    $data = $service->getInventoriesTree();

    // Excel::store(new SummaryInventoryReport($data), 'inventory_report.xlsx', 'public');
    return $data;
});

Route::get('pusher-check', function () {
    (new \App\Services\PusherNotification)->send(
        channel: 'my-channel-42',
        event: 'handle-export-import-notification-new',
        payload: [
            'type' => 'exportImportSuccess',
            'message' => 'Success import data',
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

// define route to approve or reject project deal price changes
Route::get('project/deal/change/price/approve', [FinanceController::class, 'approvePriceChanges'])
    ->name('project.deal.change.price.approve')
    ->middleware('signed');
Route::get('project/deal/change/price/reject', [FinanceController::class, 'rejectPriceChanges'])
    ->name('project.deal.change.price.reject')
    ->middleware('signed');

Route::get('trying', function () {
    abort(400);
});
Route::get('test', function () {
    $task = (new InteractiveProjectTaskRepository)->show(
        uid: 'afd4e13b-ed4c-480c-bfaf-4928c5399507',
        select: 'id,intr_project_id',
        relation: [
            'pics:id,task_id,employee_id',
            'holdStates:id,task_id,holded_at,unholded_at',
            'workStates:id,task_id,started_at,first_finish_at',
            'reviseStates:id,task_id,start_at,finish_at',
            'interactiveProject:id',
            'interactiveProject.pics:id,intr_project_id,employee_id',
            'approvalStates:id,task_id,started_at,approved_at',
        ]
    );
});

Route::get('migrate-duration', function () {
    $service = app(\Modules\Production\Services\ProjectService::class);

    return $service->migrateTaskDuration();
});
