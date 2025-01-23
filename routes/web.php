<?php

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskStatus;
use App\Jobs\PostNotifyCompleteProjectJob;
use App\Jobs\UpcomingDeadlineTaskJob;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Models\ProjectTask;

Route::get('/', function () {
    $service = new PerformanceReportService();
    return $service->importEmployeePoint([
        'employee_uids' => ['d0d9ffab-bf58-488b-87bb-a8c9c2fb2978'],
        'all_employee' => 1,
        'start_date' => '2024-12-25',
        'end_date' => '2025-01-24'
    ]);
    return view('landing');
});

Route::get('interactive/download', function (\Illuminate\Support\Facades\Request$request) {
    $date = date('Y-m-d');
    $deviceId = request('d');
    return \Illuminate\Support\Facades\Response::download(public_path("storage/interactive/qr/{$deviceId}/{$date}/" . request('file')));
});

Route::get('created', function () {
    $customer = \Modules\Production\Models\Project::find(232);
    $customer->name = 'coba ubah';
    $observer = new \Modules\Production\Observers\NasFolderObserver();
    $observer->updated($customer);
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
