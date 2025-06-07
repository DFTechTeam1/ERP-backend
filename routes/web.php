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
    $data = [
            'quotationNumber' => 'DF01067',
            'date' => '15 April 2025',
            'designJob' => '01064',
            'address' => 'Kaca Piring 19 / 2nd level',
            'city' => 'Surabaya - East Java',
            'phone' => '(62) 8211068 6655',
            'email' => 'dfactory.id@gmail.com',
            'social' => ['@dfactory_', '@dfactory'],
            'client' => [
                'name' => 'Majestic EO',
                'address' => 'Surabaya Indonesia'
            ],
            'event' => [
                'title' => 'The Wedding Reception of Mr. Kevin & Mrs. Jocelyn',
                'date' => '24 Mei 2025',
                'venue' => 'Imperial Ballroom - Surabaya'
            ],
            'items' => [
                ['LED Visual Content Content Media Size :', 'Rp 57.500.000,-'],
                ['Main Stage : 5x15 m', '']
            ],
            'ledDetails' => [
                'Opening Sequence Content',
                'LED Digital Backdrop Content',
                'Entertainment LED Concept',
                '  a. Entertainer Session',
                '  b. Entertainer Bumper',
                'Event Stationary',
                '  a. Animated Logo Content',
                '  b. Procession Title'
            ],
            'terms' => [
                'Minimum Down Payment sebesar 50% dari total biaya yang ditagihkan, biaya tersebut tidak dapat dikembalikan.',
                'Pembayaran melalui rekening BCA 188 060 1225 a/n Wesley Wiyadi / Edwin Chandra Wilaya',
                'Biaya diatas tidak termasuk pajak.',
                'Biaya layanan diatas hanya termasuk perlengkapan multimedia DFACTORY dan tidak termasuk persewaan unit LED dan sistem multimedia lainnya bila diperlukan.',
                'Biaya diatas termasuk Akomodasi untuk Crew bertugas di hari-H event.'
            ],
            'currentDate' => now()->format('d F Y')
        ];


    $image = 'https://data-center.dfactory.pro/dfactory.png';
    // $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('quotation.quotation', $data)
    //     ->setPaper('14')
    //     ->setOption('isHtml5ParserEnabled', true)
    //     ->setOption('isRemoteEnabled', true);;

    // return $pdf->download('quotation.pdf');
    $creator = new \App\Pdf\PdfCreator();
    return $creator->render();
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
