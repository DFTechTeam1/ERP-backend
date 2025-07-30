<?php

use App\Actions\Project\WriteDurationTaskHistory;
use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Production\TaskStatus;
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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
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
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;
use Modules\Production\Models\ProjectTask;

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

Route::get('check', function () {
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\SummaryFinanceExport, 'summary.xlsx');

    return view('finance::reports.summaryExport');

    return (new \App\Services\GeneralService)->getFinanceExportData(payload: [
        'date_range' => '2025-07-01 - 2025-07-30'
    ]);
});

Route::get('expired', function () {
    return view('errors.expired');
});

Route::get('i/p', function () {
    return view('invoices.approved');
})->name('invoices.approved');
Route::get('i/r', function () {
    return view('invoices.rejected');
})->name('invoices.rejected');