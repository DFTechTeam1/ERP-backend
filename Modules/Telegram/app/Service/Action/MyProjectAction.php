<?php

/**
 * Callback data should be write on this way
 * idt={IDENTITY}&v={CURRENT_VALUE}&y={CURRENT_YEAR}&m={CURRENT_MONTH}&pid={CURRENT_PROJECT_ID}
 */

namespace Modules\Telegram\Service\Action;

use App\Models\User;
use App\Services\Telegram\InlineKeyboard;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Telegram\Enums\CallbackIdentity;

class MyProjectAction {
    private $service;

    private $token;

    private $chatId;

    private $messageId;

    private $year;

    private $month;

    private $pid;

    private $currentFunction;

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    protected function setProjectParams(array $payload)
    {
        parse_str($payload['callback_query']['data'], $queries);

        $this->year = $queries['y'];
        $this->month = $queries['m'];
        $this->pid = $queries['pid'];
        $this->currentFunction = $queries['f'];
    }

    protected function setService()
    {
        $this->service = new TelegramService();
    }

    protected function setAuth()
    {
        if (!$this->token) {
            $employee = Employee::select('id')
                ->where('telegram_chat_id', $this->chatId)
                ->first();
            $user = User::where('employee_id', $employee->id)->first();
            $role = $user->getRoleNames()[0];
            $roles = $user->roles;
            $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

            $token = $user->createToken($role, $permissions, now()->addHours(2));

            $this->token = $token->plainTextToken;
        }
    }

    protected function sendProjectList()
    {
        // get the projects
        $startDate = $this->year . '-' . $this->month . '-01';
        $year = $this->year;
        $month = $this->month;
        $getLastDay = Carbon::createFromDate((int) $year, (int) $month, 1)
            ->endOfMonth()
            ->format('d');
        $endDate = $year . '-' . $month . '-' . $getLastDay;

        $this->setAuth();

        $search = [
            'search' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'filter_month' => false,
            'filter_today' => false,
            'filter_year' => false,
            'page' => 1,
            'itemsPerPage' => 100
        ];

        $response = Http::withToken($this->token)
            ->get(env("TELEGRAM_DOMAIN") . "/api/production/project", $search);

        Log::debug('response project', [$response->json()]);

        if ($response->successful()) {
            $projects = collect($response->json()['data']['paginated'])->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name']
                ];
            })->toArray();
        }

        $projectChunks = array_chunk($projects, 2);
        $outputProjects = [];
        foreach ($projectChunks as $keyChunk => $projectData) {
            foreach ($projectData as $k => $project) {
                $outputProjects[$keyChunk][] = [
                    'text' => $project['name'],
                    'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=projectdtl&y=' . $this->year . '&m=' . $this->month . '&pid=' . $project['id']
                ];
            }
        }
        // append action to back
        $outputProjects[count($projectChunks)] = [
            [
                'text' => 'Back',
                'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=back&y=' . $this->year . '&m=' . $this->month . '&pid='
            ]
        ];

        Log::debug('outputprojects', $outputProjects);

        $send = $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
            'inline_keyboard' => $outputProjects,
        ]);
        Log::debug('send res edit', $send);
    }

    protected function sendProjectInformation()
    {
        $project = \Modules\Production\Models\Project::selectRaw('*')
            ->with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,nickname'
            ])
            ->where('id', $this->pid)
            ->first();
        $projectDate = date('d F Y', strtotime($project->project_date));
        $pics = collect($project->personInCharges)->pluck('employee.nickname')->toArray();
        $pics = implode(',', $pics);

        $message = "Nama event: {$project->name}\n";
        $message .= "Tanggal: {$projectDate}\n";
        $message .= "PIC: " . $pics;

        // get current active task
        $taskMessage = "Kamu tidak memiliki tugas yang aktif di event ini";
        $taskPic = \Modules\Production\Models\ProjectTaskPic::selectRaw('employee_id,project_task_id,id')
            ->with([
                'task:id,name'
            ])
            ->whereHas('employee', function ($q) {
                $q->where('telegram_chat_id', $this->chatId);
            })
            ->whereHas('task', function ($qTask) {
                $qTask->where('project_id', $this->pid);
            })
            ->get();
        if (count($taskPic) > 0) {
            $taskMessage = "Kamu mempunyai tugas: \n";
            foreach ($taskPic as $key => $pic) {
                $taskMessage .= $key + 1 . '. ' . $pic->task->name . "\n";
            }
        }

        $message .= "\n\n";
        $message .= $taskMessage;

        $this->service->sendTextMessage($this->chatId, $message, true);
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity($payload);
        $this->setProjectParams($payload);
        $this->setService();

        if ($this->currentFunction == 'month') {
            $this->sendMonthList(true);
        } else if ($this->currentFunction == 'year') {
            $this->sendYearList(true);
        } else if ($this->currentFunction == 'project') {
            $this->sendProjectList();
        } else if ($this->currentFunction == 'back') {
            if ($this->year && !$this->month) { // this action came when user click back on the month list, so take to year list
                $this->sendYearList(true);
            } else if ($this->year && $this->month) { // this action came when user click back on the project list, so take to the month list
                $this->sendMonthList(true);
            }
        } else if ($this->currentFunction == 'projectdtl') {
            $this->sendProjectInformation();
        }
    }

    protected function sendYearList(bool $isEdit = false)
    {
        $projects = \Modules\Production\Models\Project::selectRaw('distinct year(project_date) as year')
            ->get();

        $years = collect($projects)->pluck('year')->toArray();

        // chunk to 2
        $chunks = array_chunk($years, 2);

        $outputYear = [];
        foreach ($chunks as $key => $chunk) {
            foreach ($chunk as $year) {
                $outputYear[$key][] = [
                    'text' => $year,
                    'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=month&v=' . $year . '&y=' . $year . '&m=&pid=',
                ];
            }
        }

        if ($isEdit) {
            $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
                'inline_keyboard' => $outputYear
            ]);
        } else {
            $this->service->sendButtonMessage($this->chatId, 'Pilih dulu beberapa pilihan dibawah ini ya', [
                'inline_keyboard' => $outputYear
            ]);
        }
    }

    protected function sendMonthList(bool $isEdit = false)
    {
        $button = [
            'inline_keyboard' => [
                [
                    ['text' => 'Januari', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=01&pid='],
                    ['text' => 'Febuari', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=02&pid='],
                    ['text' => 'Maret', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=03&pid='],
                ],
                [
                    ['text' => 'April', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=04&pid='],
                    ['text' => 'Mei', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=05&pid='],
                    ['text' => 'Juni', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=06&pid='],
                ],
                [
                    ['text' => 'Juli', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=07&pid='],
                    ['text' => 'Agustus', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=08&pid='],
                    ['text' => 'September', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=09&pid='],
                ],
                [
                    ['text' => 'Oktober', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=10&pid='],
                    ['text' => 'November', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=11&pid='],
                    ['text' => 'Desember', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=project&y=' . $this->year . '&m=12&pid='],
                ],
                [
                    ['text' => '<< Back', 'callback_data' => 'idt=' . CallbackIdentity::MyProject->value . '&f=back&y=' . $this->year . '&m=&pid='],
                ]
            ]
        ];

        $send = $this->service->sendEditButtonMessage($this->chatId, $this->messageId, $button);

        Log::debug('send month list', $button);
    }
}
