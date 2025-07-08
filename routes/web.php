<?php

use App\Actions\Project\WriteDurationTaskHistory;
use App\Enums\Production\TaskStatus;
use App\Http\Controllers\Api\InteractiveController;
use App\Http\Controllers\LandingPageController;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Http\Controllers\Api\InvoiceController;
use Modules\Finance\Jobs\TransactionCreatedJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Repository\InvoiceRepository;
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
Route::get('invoices/download', [InvoiceController::class, 'downloadInvoice'])->name('invoice.download')
    ->middleware('signed');

Route::get('/notification-preview', function () {
    $transaction = \Modules\Finance\Models\Transaction::latest()
        ->with([
            'projectDeal:id,name,project_date,is_fully_paid',
            'projectDeal.transactions',
            'projectDeal.finalQuotation',
            'invoice:id,payment_due'
        ])
        ->first();
 
    TransactionCreatedJob::dispatch($transaction->id);

    // return (new \App\Services\GeneralService)->getUpcomingPaymentDue();
});

Route::get('check', function () {
    $response = WriteDurationTaskHistory::run(1609);

    return $response;
});