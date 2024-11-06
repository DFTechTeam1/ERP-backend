<?php

namespace Modules\Telegram\Service\Action;

use App\Enums\Production\TaskStatus;
use App\Enums\Telegram\ChatStatus;
use App\Enums\Telegram\ChatType;
use App\Enums\Telegram\CommandList;
use App\Models\User;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Models\Employee;
use Modules\Production\Services\ProjectService;
use Modules\Telegram\Enums\CallbackIdentity;
use Modules\Telegram\Models\TelegramChatHistory;
use Modules\Telegram\Models\TelegramReportTask;

class MyTaskAction {
    private $service;

    private $chatId;

    private $messageId;

    private $year;

    private $month;

    private $tid;

    private $pid;

    private $currentFunction;

    private $token;

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

    protected function setUserIdentity(array $payload, bool $isFromText = false)
    {
        if (!$isFromText) {
            $this->chatId = $payload['callback_query']['message']['chat']['id'];
            $this->messageId = $payload['callback_query']['message']['message_id'];
        } else {
            $this->chatId = $payload['message']['chat']['id'];
            $this->messageId = $payload['message']['message_id'];
        }
    }

    protected function setTaskParam(array $payload)
    {
        parse_str($payload['callback_query']['data'], $queries);

        $this->tid = $queries['tid'];
        $this->currentFunction = $queries['f'];
        if (isset($queries['m'])) {
            $this->month = $queries['m'];
        }

        if (isset($queries['y'])) {
            $this->year = $queries['y'];
        }

        if (isset($queries['pid'])) {
            $this->pid = $queries['pid'];
        }
    }

    protected function setService()
    {
        $this->service = new TelegramService();
    }

    protected function validateNasLink(string $link)
    {
        $pattern = '/^(?!.*(https:\/\/))(?=.*(http:\/\/|\\\\192*|file:\/\/)).+$/';

        if (preg_match($pattern, $link)) {
            return true;
        } else {
            return false;
        }
    }

    public function handleContinueCommand(array $payload, object $currentChatData)
    {
        $this->setUserIdentity($payload, true);
        $this->setService();

        if ($currentChatData->current_function == 'sendApproveWork') {
            $this->handleProofOfWork($payload, $currentChatData);
        }
    }


    public function handleProofOfWork(array $payload, object $currentTopicData)
    {
        // validate NAS LINK
        if (!$this->validateNasLink($payload['message']['text'])) {
            $this->service->sendTextMessage($this->chatId, 'Wahh link yang kamu berikan tidak sesuai');
        } else {

            // update
            TelegramReportTask::where('telegram_chat_id', $this->chatId)
                ->where('task_id', $this->tid)
                ->update([
                    'nas_link' => $payload['message']['text']
                ]);
        }
    }

    public function handle(array $payload)
    {
        $this->setUserIdentity($payload);
        $this->setTaskParam($payload);
        $this->setService();

        if ($this->currentFunction == 'back') {
            $name = 'sendOptionTask';
            $this->sendOptionTask();
        } else if ($this->currentFunction == 'event') {
            $name = 'sendBasedOnEvent';
            $this->sendBasedOnEvent();
        } else if ($this->currentFunction == 'backoption') {
            $name = 'sendOptionTask';
            $this->sendOptionTask();
        } else if ($this->currentFunction == 'month') {
            $name = 'sendMonthList';
        } else if ($this->currentFunction == 'backyear') {
            $name = 'sendYearList';
        } else if ($this->currentFunction == 'eventlist') {
            $name = 'sendEventOnSelectedMonth';
        } else if ($this->currentFunction == 'backmonth') {
            $name = 'sendMonthList';
        } else if ($this->currentFunction == 'projectdtl') {
            $name = 'sendTaskList';
        } else if ($this->currentFunction == 'detailtask') {
            $name = 'sendDetailTaskAction';
        } else if ($this->currentFunction == 'apptask') {
            $name = 'sendApproveTask';
        } else if ($this->currentFunction == 'deadline') {
            $name = 'sendDeadlineBased';
        } else if ($this->currentFunction == 'reportdone') {
            $name = 'sendApproveWork';
        }

        $this->$name();
    }

    protected function sendApproveWork()
    {
        // send nas link confirmation
        TelegramReportTask::create([
            'task_id' => $this->tid,
            'telegram_chat_id' => $this->chatId,
        ]);

        // delete
        $this->service->sendTextMessage($this->chatId, 'Kirim link NAS hasil pekerjaan kamu', true);
        $this->service->reinit();
        $this->service->deleteMessage($this->chatId, $this->messageId);
    }


    protected function sendDeadlineBased()
    {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+7 days'));
        $tasks = \Modules\Production\Models\ProjectTask::selectRaw('id,name')
            ->where('start_date', '>=' , $startDate)
            ->where('end_date', '<=', $endDate)
            ->get();

        $outputTasks = [];
        $chunks = array_chunk($tasks->toArray(), 2);
        foreach ($chunks as $key => $chunk) {
            foreach ($chunk as $task) {
                $outputTasks[$key][] = [
                    'text' => $task['name'],
                    'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=detailtask&tid=' . $task['id'] . '&y=&m=&pid=',
                ];
            }
        }

        $outputTasks[count($chunks)] = [
            [
                'text' => 'Back',
                'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=back&y=&m=&tid=',
            ]
        ];

        $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
            'inline_keyboard' => $outputTasks
        ]);
    }

    protected function sendApproveTask()
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,uid,project_id')
            ->with(['project:id,uid'])
            ->find($this->tid);

        $this->setAuth();

        $response = Http::withToken($this->token)
            ->get(env('APP_URL') . "/api/production/project/{$task->project->uid}/task/{$task->uid}/approve");

        if ($response->successful()) {
            $this->service->deleteMessage($this->chatId, $this->messageId);

            $this->service->reinit();

            $this->service->sendTextMessage($this->chatId, $response->json()['message']);
        } else {
            $this->service->sendTextMessage($this->chatId, 'Waahh, gagal untuk approve tugas');
        }
    }

    protected function sendDetailTaskAction()
    {
        try {
            $task = \Modules\Production\Models\ProjectTaskPic::selectRaw('id,project_task_id,employee_id')
                ->with([
                    'task:id,name,status'
                ])
                ->where('project_task_id',$this->tid)
                ->first();

            $outputButton = [];
            if ($task->task->status == TaskStatus::WaitingApproval->value) {
                $outputButton = [
                    [
                        ['text' => 'Approve task', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=apptask&tid=' . $this->tid]
                    ]
                ];
            } else if ($task->task->status == TaskStatus::OnProgress->value || $task->task->status == TaskStatus::Revise->value) {
                $outputButton = [
                    [
                        ['text' => 'Sudah Selesai', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=reportdone&tid=' . $this->tid]
                    ]
                ];
            }

            $key = 0;
            if (count($outputButton) > 0) {
                $key = 1;
            }

            $outputButton[$key] = [
                [
                    'text' => 'Back',
                    'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=projectdtl&y=' . $this->year . '&m=' . $this->month . '&pid=' . $this->pid . '&tid='
                ]
            ];

            $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
                'inline_keyboard' => $outputButton
            ]);
        } catch (\Throwable $th) {
            Log::error($th
            );
            $this->service->sendTextMessage($this->chatId, 'Wahh, aku belum bisa memproses pesan mu nihh', true);
        }
    }

    protected function sendTaskList()
    {
        $tasks = \Modules\Production\Models\ProjectTaskPic::selectRaw('project_task_id,employee_id')
            ->with([
                'task:id,name,status,end_date,start_date'
            ])
            ->whereHas('employee', function ($q) {
                $q->where('telegram_chat_id', $this->chatId);
            })
            ->whereHas('task', function ($qtask) {
                $qtask->where('project_id', $this->pid);
            })
            ->get();

        $chunks = array_chunk($tasks->toArray(), 2);

        $outputTasks = [];
        foreach ($chunks as $key => $chunk) {
            foreach ($chunk as $task) {
                $outputTasks[$key][] = [
                    'text' => $task['task']['name'],
                    'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=detailtask&tid=' . $task['task']['id'] . '&y=' . $this->year . '&m=' . $this->month . '&pid=' . $this->pid,
                ];
            }
        }

        $outputTasks[count($chunks)] = [
            [
                'text' => 'Back',
                'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m='. $this->month .'&tid=',
            ]
        ];

        $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
            'inline_keyboard' => $outputTasks
        ]);
    }

    protected function sendEventOnSelectedMonth()
    {
        $employee = Employee::select('id')
            ->where('telegram_chat_id', $this->chatId)
            ->first();
        $user = User::where('employee_id', $employee->id)->first();

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
            'page' => 1,
            'itemsPerPage' => 100
        ];

        $response = Http::withToken($this->token)
            ->get(env("APP_URL") . "/api/production/project", $search);

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
                    'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=projectdtl&y=' . $this->year . '&m=' . $this->month . '&pid=' . $project['id'] . '&tid='
                ];
            }
        }
        // append action to back
        $outputProjects[count($projectChunks)] = [
            [
                'text' => 'Back',
                'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=backmonth&y=' . $this->year . '&m=' . $this->month . '&tid='
            ]
        ];

        $send = $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
            'inline_keyboard' => $outputProjects,
        ]);
    }

    protected function sendMonthList()
    {
        $button = [
            'inline_keyboard' => [
                [
                    ['text' => 'Januari', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=01&tid='],
                    ['text' => 'Febuari', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=02&tid='],
                    ['text' => 'Maret', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=03&tid='],
                ],
                [
                    ['text' => 'April', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=04&tid='],
                    ['text' => 'Mei', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=05&tid='],
                    ['text' => 'Juni', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=06&tid='],
                ],
                [
                    ['text' => 'Juli', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=07&tid='],
                    ['text' => 'Agustus', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=08&tid='],
                    ['text' => 'September', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=09&tid='],
                ],
                [
                    ['text' => 'Oktober', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=10&tid='],
                    ['text' => 'November', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=11&tid='],
                    ['text' => 'Desember', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=eventlist&y=' . $this->year . '&m=12&tid='],
                ],
                [
                    ['text' => '<< Back', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=backyear&y=' . $this->year . '&m=&tid='],
                ]
            ]
        ];

        $this->service->sendEditButtonMessage($this->chatId, $this->messageId, $button);
    }

    protected function sendBasedOnEvent()
    {
        $this->sendYearList(true);
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
                    'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=month&v=' . $year . '&y=' . $year . '&m=&tid=',
                ];
            }
        }

        $outputYear[count($chunks)] = [
            [
                'text' => 'Back',
                'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=backoption&y=' . $this->year . '&m=' . $this->month . '&tid='
            ]
        ];

        if ($isEdit) {
            $send = $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
                'inline_keyboard' => $outputYear
            ]);
        } else {
            $send = $this->service->sendButtonMessage($this->chatId, 'Pilih dulu beberapa pilihan dibawah ini ya', [
                'inline_keyboard' => $outputYear
            ]);
        }
    }

    protected function sendOptionTask()
    {
        $this->service->sendEditButtonMessage($this->chatId, $this->messageId, [
            'inline_keyboard' => [
                [
                    ['text' => '1 minggu lagi harus selesai', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=deadline&tid=']
                ],
                [
                    ['text' => 'Berdasarkan event', 'callback_data' => 'idt=' . CallbackIdentity::MyTask->value . '&f=event&tid='],
                ]
            ]
        ]);
    }


}
