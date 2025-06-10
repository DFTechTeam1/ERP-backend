<?php

use App\Enums\Production\TaskStatus;
use App\Http\Controllers\Api\InteractiveController;
use App\Http\Controllers\LandingPageController;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
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

Route::get('quotation', function () {
    $service = new \App\Services\GeneralService();
    $data = [
            'rules' => $service->getSettingByKey('quotation_rules'),
            'company' => [
                'address' => $service->getSettingByKey('company_address'),
                'email' => $service->getSettingByKey('company_email'),
                'phone' => $service->getSettingByKey('company_phone'),
                'name' => $service->getSettingByKey('company_name'),
            ],
            'quotationNumber' => 'DF01067',
            'date' => '15 April 2025',
            'designJob' => '01064',
            'price' => 'Rp100,000,000',
            'client' => [
                'name' => 'Majestic EO',
                'city' => 'Surabaya',
                'country' => 'Indonesia'
            ],
            'event' => [
                'title' => 'The Wedding Reception of Mr. Kevin & Mrs. Jocelyn',
                'date' => '24 Mei 2025',
                'venue' => 'Imperial Ballroom - Surabaya'
            ],
            'ledDetails' => [
                ['name' => 'Main Stage', 'size' => '5 x 5 m'],
                ['name' => 'Main Stage', 'size' => '5 x 5 m'],
                ['name' => 'Main Stage', 'size' => '5 x 5 m'],
                ['name' => 'Main Stage', 'size' => '5 x 5 m'],
            ],
            'items' => [
                'Opening Sequence Content',
                'LED Digital Backdrop Content',
                'Entertainment LED Concept',
                'Event Stationary',
            ],
        ];


    $image = 'https://data-center.dfactory.pro/dfactory.png';
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('quotation.quotation', $data)
        ->setPaper('14')
        ->setOption([
            'defaultFont' => 'sans-serif',
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'debugPng' => false,
            'debugLayout' => false,
            'debugCss' => false
        ]);

    $filename = "Quotation Majestic EO.pdf";
    return $pdf->stream(filename: $filename);
    // return view('quotation.quotation');
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
