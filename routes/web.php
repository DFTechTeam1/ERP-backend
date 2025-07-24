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
Route::get('invoices/download', [InvoiceController::class, 'downloadInvoice'])->name('invoice.download')
    ->middleware('signed');
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
    // return (new TelegramService)->sendTextMessage(
    //     chatId: '1991941955',
    //     message: "Test message",
    // );
    // $currentChanges = InvoiceRequestUpdate::selectRaw('id,request_by,amount,payment_date,invoice_id,approved_at')
    //     ->with([
    //         'user:id,email,employee_id',
    //         'user.employee:id,name',
    //         'invoice:id,parent_number,number'
    //     ])
    //     ->latest()
    //     ->first();
    // return (new ApproveInvoiceChangesNotification($currentChanges))
    //     ->toMail('gumilang.dev@gmail.com');
    // $current = InvoiceRequestUpdate::latest()->first();
    // if (!$current) {
    //     $invoice = Invoice::latest()->first();
    //     DB::table('invoice_request_updates')->insert([
    //         'invoice_id' => $invoice->id,
    //         'payment_date' => now()->addDays(3)->format('Y-m-d'),
    //         'status' => InvoiceRequestUpdateStatus::Pending->value,
    //         'request_by' => User::latest()->first()->id,
    //     ]);
    //     $current = InvoiceRequestUpdate::latest()->first();
    // }

    // RequestInvoiceChangeJob::dispatch($current);

    $string = "eyJjaXBoZXJ0ZXh0IjoiYlRtV1pZcTFFaVd0QTEzRFZWSUdnWCtoMTl4UDA1b3ZkaTg2aEpEcTNBMkdzUmR6TE9qcDBMVnBTeWxyaE9lY0VzMHJoZEx3eVZMY0lqTmhFUlNPdnE0anhVbGpoZDQ5Z1p6dklzdlFLVWdFUWpsZXNiazNDY2I2Q3IrS3lwUnFNVnNtaWk3c0VMbjg0a0NaQmdURUl0UHhueUNaRXNaV1BHTjZuRktMVWczZytnWnN2SzFBWnlkd3RvTXdyNVFvQTg4UlU2UjJ1a24xVmZjME1aTlRcL3IyVlVNenpENm9DNVBoUDJXTTFNSEk1V1EzU3lldTJpa2E2U1RiUWVcL1ZpeEo0eHJJRmtFcDRVeURScTFHbXdSYUR2M1ZWemdWeGduWExJNGdKN0RSRmljbDFOMXhiVlh4cGpZN01wWDFpWndyVUM0NGdQV3FRWHIxNDJJeDROMnZTamM0MUh3ODgxWjE5cGFySVk2cjZFaXk0dWxjbTZveE0yZXIxOVNldTNhY01ONkEyVitLaThJNCtqN0NVNVIwYjZlRmMxRTFJanZYYzlGUnl3S0I4TEV2MVpYanJzdUtJUGZmXC81ODdwUlNsdmFZOFdUYjM2M0RwQTVBZ3g4NzlXU3o2ZFpEZWwrNnI4Q3hPdWZwS0tTVlVyakltaER5QnBDZ01pN0tiQm50WFZ1cE9ndTRlQ0xqRzZYQWVHTWZHZlM4eTMrN2I2SEF3ZzBjZjNDejZEVkZWTFJvQW5WUlhDMXZCQm5uRUZZcVMrMng4dlpHN2dhZ3crRHd1MUJGS0I5eXJRY1lyaWVuYTVLdXBnYk9NTGJSTTE1dWEwZ3R3ZHJNQjVhOGo4OVY3MnppOG5mMnlxbFwvb1BmNVdmQnJ6YjJHS0xZd2dubStcL0RBcExPVzFvYXR5dnRUaHFha3NRRUEyNTh4cUU4NWRPdTNBZk9cLzRiRmtBTHlPZ2J6dFRlQkx0WnlwSGhGOEFodVNkdmxZUkwwQWhFRXdKV2h5ejN3TmlpZkxPQ0ZONG9NVGw0UlBTUXRRRUtVdjM5SzBcLzZ0ejZPMFI2TFl3TDhOaFFzY1Y1N2UzMkxBQ1EwODJHUzQ0c2pRQWpHVzNMV0VDTjd2WDRZd0NjTkFmTDdPUzdCakNYaW5adW5OeWRKUHZIYUhCaXoxNFwvMUlMTklqekpZcHJrMkM3Slp1K2g2clwvWUpFRE5aQzFkZU1QV1V6c2hJb3pKK05hbWFDajlPUlozdFQxMGVqelhNdUlaNldpeUtKRVR3NmtXNFcyNjZWbjcyWjdNN3pFc2pHa0dpYlZpRDl4T0RrSGF3eVhzUDJuUUhkdmo3Q2lHK2d0b1Jrc05BcUQ4OTBoSzl2ZGFkU2FIVk1TVDBpWmJqeXE0MHR1aEhzV1F6cWRYZ0RWVGRYbzZBNmx2SWFVUm5xZzNBMzc0bDlQaFV5SGM2YVh6RHRqcENIWFNIdXF1ZGNWYW9VdVNPR3locFl6QkFQYkdRUXNPOFVZRWRXejFBbXBJS3R2MDR4OXJqRE5oV0ZRUTVtZmVMd25oWDR3bUhOc0VBZzNNZXBCMllqdDh6ZlNjN3orNzZ1OW1oY1VWRCttemN3QlBXSko4cWZ6VHlKZUF4K3hmbDhaWmRjbmhWRUJhdllzakg2SlQ0Zlp6THBzXC9aNVkwVUVBeHpuRW55Tk9nMUR3NFBSdGIyTUl3WE90Z1laSlZWMEpIYm5LXC9Cc3dFNSt0K2p4ZGpyRHZrRWljM1V3anUrNlhNZXN4VXozXC9rNWs3Q3NKY3A5dzdJblREV2xoMm50azJBeTJ4Q2srNVJtV0h1OUpyZEdUSFpDeEVZdk1uamlYUktETnpqVTBya0dcL202VUs0Ump1RVRZS2E1N3JmMnRtT09ZaTlZWFA5MTNOa1VjNHBMeDJLUG13MjFmYlZcLzJGWjR2c2RzQ2JFNEpWazducFY2UGRycHBVTjZaVHJaUlB5ZG5oK0FHc1oxUlNxdnZLcmN3UVwvcElFNVdNRHY0XC9SbkJMRDdoMXpDdUFcL3M4MDVSS1QxSE03T1h0ZUs1a0ltUU15Skk2TVQrdW5PamFGK2NrSHA5SFRBTVdlMWdhOWt6eUhmMEZXVHpnUFJMT0lnZzdjclR0dzZETWMyN3dRV0NTZ3Vic21cL3JHekYxUTM1SFJJNjBDZVZoNm9JNXdwcitmT2lOeXJqcHJRSjJUTEpMZTFkVGU5Z2RMTVwvOFdMR0VLS0VqTkNvM1NJeHlYQk5KblJudktiTmxPTDFnYlJEdGFiTktWTnFsOXN5WFlcL281OTRcL2ZXTjh3K1h0XC9BUk50Qm82amdXYVlCWlBOTzdOSDBoYm05bnZ5SkxWcW5iWHNRYzlxODZHcGpvS2FVMllQWEtXcW9iXC92ak9IbVJuS2lkK3FYMkprN3Z6d0U5dTYzSHVyaDV6ZnVWb3VTZEFBcFwvRG52Y2tRVThKUlBmbkJCcnFxRGJvMHRBY29DdXhVbFlFS2pzYUFLcmE4bWhKVktJdmNWTUNhNzc2eElaczdGeFwvNkJVaDVKV1JtdzRCSWR5cTZjb2pobEtGeHEwRTFWZVB0eHBseUhIbHNjNUlLRkZvWE5cL1lKRzNmZmhzQ1JcL2pxaW9YRFwvQWhmbm1zNnpRY0M3aGdkbHlSUEs3dHQycmN1bmxRY1lQaTBGdDM1eE5nNWVKT29CVFhIQmR4T0VzeG9jSlQxMDRMbThIc0JaK3JrT05NSlM0eEh3c1I4ZFA5RTZ2dkxBTjJrYUszY0FqWDBZemM2NkVLSTVrXC9rd2k2UllGZHJ2cSsrOVRPa2lWUzdtYzhkcmxLa3JYcHdqUzR6bUpwY0FcL2JBWXp1OVJHNWwwek1XcjZqUmNVV0VhaHdZRHd6VVJ6T0J2WXE1VFpvXC82UkN2MHJlSGdUTFwvY3A4d2k1RUZ5ZEk5cTBFN1JmUEZNNzEzdDhBWTN4M0hIcUpWZkpiTGVXb3ZIN1Qza2M1NFZOMG1BeDhcL2c1a0JEY1wvaXhsczc2XC9jR1ZMeW9IbWg5YjhVRWw2Tys0dkk3SzQwSWM9IiwiaXYiOiI0NDIwMWQyZWViYjQxY2Q0NjBjYzI0OGY1ZWFlMmIwMSIsInNhbHQiOiI5NWRmZDRkYjRlYTA3MDY4YThkOWU4ZWI5MDZjYjI1MDY3YjM2NzY1YWE2YTU1M2IxZTk5NTQ3YWFiYzYyNmU1NzExYTIxMDNmYTQ4YzZjODgzODE3YWQ5MDg1M2UzMGQ1NDFjNGIwOWQxOTZjNjU0YjZkYjM0OWQ4YjQ1ODEyNDJkY2MwMTM4YzY1MDQ5NzU2NmYyNTlhNzc4ODA1NzAxMjQ5NjQ2ZjM3M2YxZDBhZjc0ZmJmM2I5NGFjNzBlMzljNjg2ZWZhYWVjYTI0ODJkZjBiZTE2ZTc5OWQ2YWQzMDEzY2U5ZGZiOTdlZTk2NTc4MzI5Mjg1MWIyZGUyYWQ3ZDZjMzU0MTg4YzdiMjUyNzljOWJjZDYzZjRiOThkOTE4YjkyNWEyNGNkNDllMTk4N2MxMzZjZTQ5ODZjMmIwNDJhZDkzYjdjZGViMjgxMTBkZjViNDBjYTYxNDY3NDE2NDI4ZWY1NjRiMmE3YTE2Y2JiNzc1MjExMGYyMTIzMzVmMDhhZDU2YTllMzI2ODBhNmYwYmJmNjMzNDM4MmFjNzU0N2E2N2VmNjA0Y2UwMTc0YWUzMDZmMDNmYjRiMmFiNDA1MzVjODIzYzA5OGYwMWYxMTdhZDA1ZmQxNTU2OTBiY2FkOTVlY2U5ZTY2MjE2NDA0Y2E1NWJmMWE5ZDExNSIsIml0ZXJhdGlvbnMiOjk5OX0=";
    return (new EncryptionService)->decrypt($string, env('SALT_KEY'));
});

Route::get('expired', function () {
    return view('errors.expired');
});