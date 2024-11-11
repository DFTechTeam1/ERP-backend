<?php

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskStatus;
use App\Jobs\PostNotifyCompleteProjectJob;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Production\Models\ProjectTask;

Route::get('/', function () {
    return view('landing');
});

Route::get('telegram', function () {
    $chatId = '1991941955';
    $service = new TelegramService();
    return $service->sendButtonMessage($chatId, 'Kirim lokasimu sekarang juga!', [
        'keyboard' => [
            [
                ['text' => 'Lokasi', 'request_location' => true]
            ]
        ],
        'is_persistent' => true,
        'one_time_keyboard' => true,
        'resize_keyboard' => true,
    ]);
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
    $barcode = (new \Picqer\Barcode\Types\TypeCode128())->getBarcode('https://google.com');
    $renderer = new \Picqer\Barcode\Renderers\PngRenderer();
    $renderer->setForegroundColor($colorRed);
    file_put_contents('barcode.png', $renderer->render($barcode, 300, 80));

    $image = asset('barcode.png');
    return view('testing_barcode', compact('image'));
});

Route::get('generate-official-email', [\App\Http\Controllers\Api\TestingController::class, 'generateOfficialEmail']);

Route::get('trigger', function () {
    $pusher = new \App\Services\PusherNotification();

    $pusher->send('channel-interactive-new', 'notification-event', ['message' => 'Hello']);
});

Route::get('ilham', function () {
    $endDate = date('Y-m-d', strtotime('+2 days'));

    $tasks = ProjectTask::selectRaw('id,uid,project_id,name')
        ->with([
            'pics:id,project_task_id,employee_id',
            'pics.employee:id,nickname,email,line_id',
            'project:id,name'
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
