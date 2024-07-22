<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Vinkla\Hashids\Facades\Hashids;

class RequestTeamMemberNotification extends Notification
{
    use Queueable;

    private $lineIds;

    private $pic;

    private $requestedBy;

    private $player;

    private $project;

    private $transferId;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        array $lineIds, 
        object $pic, 
        object $requestedBy, 
        \Modules\Hrd\Models\Employee $player, 
        object $project,
        int $transferId
    )
    {
        $this->lineIds = $lineIds;

        $this->pic = $pic;

        $this->requestedBy = $requestedBy;

        $this->player = $player;

        $this->project = $project;

        $this->transferId = $transferId;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [\App\Notifications\LineChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://laravel.com')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }

    public function toLine($notifiable)
    {
        $playerData = $this->player['nickname'];

        $divider = 107;

        $tokenData = Hashids::encode(
             $this->player['id'] . $divider . $this->project->id . $divider . $this->requestedBy->id . $divider . $this->transferId
        );

        $postbackApprove = 'type=approveRequestTeam&data=' . json_encode([
            'pyr' => $this->player['id'],
            'pid' => $this->project->id,
            'rid' => $this->requestedBy->id,
            'tfid' => $this->transferId,
        ]);

        $transfer = \Modules\Production\Models\TransferTeamMember::find($this->transferId);

        $messages = [
            [
                'type' => 'text',
                'text' => 'Hai ' . $this->pic->nickname . ', ' . $this->requestedBy->nickname . ' request untuk meminjam ' . $playerData . ' untuk sementara dalam pengerjaan event ' . $this->project->name . ' (' . date('d F Y', strtotime($this->project->project_date)) . ') dengan alasan ' . $transfer->reason,
            ],
            [
                'type' => 'template',
                'altText' => 'Request Member Message',
                'template' => [
                    'type' => 'buttons',
                    'text' => 'Apakah kamu setuju meminjamkan ' . $playerData . ' untuk sementara waktu?',
                    'actions' => [
                        [
                            'type' => 'postback',
                            'label' => __('global.approve'),
                            'data' => $postbackApprove,
                        ],
                        [
                            'type' => 'postback',
                            'label' => __('global.reject'),
                            'data' => 'action=reject',
                            "inputOption" => "openKeyboard",
                            "fillInText" => "tokenId={$tokenData}\nalasan: ",
                        ],
                    ]
                ]
            ],
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
