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

    $string = "eyJjaXBoZXJ0ZXh0IjoiZFZtR3VjUkErMENCRTZmMHFZUFlEMWtSc2NkUkVXVnlWY0oydWpRcHBNUFlqTXFHXC9ZdUdYK3VtM0VjVlZkbTE4SlZoZkpYSXZsVmlZR0VJR09TcFB4MVBscCtlMFFHbE5LczFIQ3V5NkNEYjBsd1VieDhYVStFalNqbHBZcGw5TTI4Y2NQVzVxSGs0UXJDcDdkTVFvQ2RIWGhQRnBIYkwyQ1cyNVFYbUNUUndyV09FellJQzlSN29QXC9ZbzZsNnlGdXRQYlZobVgzblRleW1zT1JyZ0RMT0c3UDZLUEhTYW1kTWh6NXAzNGt2QmhEaGs1aGtPVTEyXC96bE9ENnczRG5pbFZRMHhcLzZTNzg2OEppSW9nWkhEZUUyTVlaVVBOemt3RUVQU0EyRytCOVwvaDVkazMyek83WUZhaFBVMlZXdE5ZSzMrZW4zek40TTc0YzVxMXNQa1E0TWM0XC83YUQ5SmltZTN3aXhPNDkwT3B1VDZBN2w5bDJOQXdvcHFueGZUTzF1Zm9LVUQxUHlxVU5DNkJcL3NBTnRrQTlrU1ZteUJ5SUk3VHowYmJVVHU0a1wveFAxbkdsOEtXY0Mxc0xVaGZ5SUNkdm9tVHZ3VThBU0ZYVkpjZVo4THY5Y3YwKzd2bG1IWXRGWU5TK0FPSVwvTEVtVDRwUnA3NGZEc3lhRWFud05iR3pVTWhOWWxnWEtFY0ZOUUcrRU1YaVBJamRDcUF6REZpNjNzcVVoNzdqUitPN2ZLcElhaVZIWjhhdXo2eFVRclVtUTdKN3R3dE5IVTNEeU1Sdk41bXRNOVRPTFl1UDhMbXhHckhDc2VSZTJ5eHRJMnZSQUxrTDZqZ0NIR3VDNFpDVXNBSGZHUEZXaldwWDBMODVNY20xT2xqOG5RcEZCQ0RKSnIrVUxKelwvN2wxZjZCajJiNHJCd01EKzkrTDY2XC9heStFcHArYTM5QVFUcVVZaUNNOFk3aHdiTk94bDlRcXhKQWZUa2tCSWVsaVh4eVpnOEh1MXVGeTdLYUFncSt2eElsTFhDczQzbEY2ZzJqUlZXQ3NLTXBiMTBDN011enlpVUVzb3JXXC9sK01LM2VsTFl0TVFjTjU0QjVsenU1WFVwTzhmQXV2T0JUc0k2bE9yQWkzeExqMzlcL0EzRWFOUWZaME8rU09cL3hlODhpQmlGYVZjN2xmR1wvbjZJaDlHYlwvSUZYK3BMdkxNT2JuR1paNlEyc2puRlBtYTdUZTNXVWZTZmZYZnRxM1wvNWdqTTlSQlBpUUVqeHZsdUhKZ2pnZkhtV0FcL1hqcVwvMStMY1wvQ0RISTdVWTVLNFNJcDdBd0Y5dGxaSEo1MXU3OU9pckJhRmhWelIxNTBEQktXSHFQV1dQeTdqNmdVZ05qNTRNVk5IYVNldm96MHFDTFFtaUZxcDJ6bjNqTTBJVGhuNWxPMU14eE5IclRcL2RURmFDTks4R1dPbGhFUmU1eWZQRUlyTVJYaWM3MWNFaXhcLytcL0ViNGkyMUdrcTdSUnlTRXh2bkhFUzdUcEJuQnB4Nmg5TmgwN2FWV09tT2pUNnA3TUhSRzV1VzBKbW93UnNFVmdxdVEwNTNVNXlNNFYrSzd3PSIsIml2IjoiM2RmODVhM2JmYzIxOWM1ZTIxMzlhMjUxMzI3ZmZjNjciLCJzYWx0IjoiNjYyNTFmNDA3NzEwOTNlYTk3OGVlZjM0MTFlYjExZWQ2YWZmODdhYTA3NmE5NDA4NTIwNGYxZTU5NGQ2ZjgwZDdkYzJkMTE3MDczNDM3N2JiMTZjZjM1MDJiNDY5Zjk2ODJjM2JjNTBkZDlkYjdlZDgwNWFmY2E4YWNlNjkwYmNjZDYwOThlMjEwY2FlNGFlOWQ3ZDViMjhiZmI4ODQ1ZGZiN2VmYjM5Mjk5ODlhY2RlNTkwZDYyODgwOWY0OWRjMjIwYTRkMDhiMDBlOWJhZjY5NWQ2N2RlZWZjOWQ3NTY4MzUxNTMzYzAwZDJhMWI5YWQ4NTA3NTMzOGM3NDhiZWNhOTI5YjQ4YzBiN2YyMzgxM2I2YzgxNzMyODlmNWVmMzJlZjIyZmI1ZGIyNDZkOGY0YTExYWJmN2NjZWZlNTBiYjNjNjNiNzY0MzU5YzA3MDRmYzhkMWY5YjMxMTU4NWRjODA1MTFlMTZjYTZiNDE2ODRlYzcyMTI2ZDgwNmNkZjFiMTQ2YzE3MTc4NTdhNjliYWU1ODI0ZDgzMDRmOGU3MDJlM2FlNGFlOTY3MzVlZDQ4YThiOTgzOTkwMDU2M2I1NDRkOWE5ZGZlYTI5N2JmZjJkYTdjNmRjY2FlZjUzM2QyMWRhMDc0NjZkMWMxYTkwMzY5YzQ5ZmQ1ZmY1YmUiLCJpdGVyYXRpb25zIjo5OTl9";
    return (new EncryptionService)->decrypt($string, env('SALT_KEY'));
});

Route::get('expired', function () {
    return view('errors.expired');
});