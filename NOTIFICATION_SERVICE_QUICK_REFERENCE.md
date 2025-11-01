# 🔔 Notification Service - Quick Reference

## Installation Checklist

- [ ] Run migrations: `php artisan notifications:table && php artisan migrate`
- [ ] Seed notification templates: `php artisan db:seed --class=NotificationSettingsSeeder`
- [ ] Configure `.env` variables (MAIL, TELEGRAM_ENDPOINT, etc.)
- [ ] Start queue worker: `php artisan queue:work`
- [ ] Add user fields: `telegram_chat_id`, `slack_webhook_url`, `notification_preferences`

---

## Basic Usage

### 1. Send to Single Channel (Database)

```php
use App\Services\NotificationService;

NotificationService::send(
    $user,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Task Name',
        'parameter3' => 'Project Name',
        'parameter4' => 'Assigner Name',
    ],
    ['database']
);
```

### 2. Send to Multiple Channels

```php
NotificationService::send(
    $user,
    'deadline_has_been_added',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Task Name',
        'parameter3' => 'Project Name',
        'parameter4' => '31 Oct 2025, 17:00',
    ],
    ['email', 'database', 'telegram']
);
```

### 3. Send Async (Recommended)

```php
NotificationService::sendAsync(
    $user,
    'task_has_been_revise_by_pic',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Task Name',
        'parameter3' => 'Project Name',
        'parameter4' => 'PIC Name',
        'parameter5' => 'Revision notes here',
    ],
    ['email', 'database', 'slack', 'telegram']
);
```

### 4. Send to Multiple Users

```php
$users = User::whereIn('id', [1, 2, 3])->get();

NotificationService::sendAsync(
    $users,
    'project_deal_has_been_approved',
    [
        'parameter1' => '{user_name}',
        'parameter2' => 'Project Name',
    ],
    ['email', 'database']
);
```

---

## Available Channels

| Channel | Const | Requirement |
|---------|-------|-------------|
| Email | `NotificationService::CHANNEL_EMAIL` | User has email |
| Slack | `NotificationService::CHANNEL_SLACK` | User has slack_webhook_url |
| Telegram | `NotificationService::CHANNEL_TELEGRAM` | User has telegram_chat_id |
| Database | `NotificationService::CHANNEL_DATABASE` | Always available |

---

## Common Options

### Email Options

```php
$options = [
    'subject' => 'Custom Subject',
    'greeting' => 'Hello!',
    'salutation' => 'Best regards',
    'action_url' => route('tasks.show', $taskId),
    'action_text' => 'View Task',
];
```

### Telegram Options

```php
$options = [
    'parse_mode' => 'HTML',
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => 'View', 'url' => route('tasks.show', $taskId)]
        ]]
    ]
];
```

### Slack Options

```php
$options = [
    'from' => 'ERP System',
    'icon' => ':bell:',
    'attachment' => [
        'title' => 'Details',
        'fields' => ['Project' => 'Name', 'Status' => 'Active'],
        'color' => '#28a745'
    ]
];
```

### Database Options

```php
$options = [
    'title' => 'Custom Title',
    'icon' => '🔔',
    'url' => route('tasks.show', $taskId)
];
```

---

## Helper Methods

### Check Channel Availability

```php
$canSendEmail = NotificationService::isChannelAvailable($user, 'email');
```

### Get User Preferences

```php
$channels = NotificationService::getUserChannels($user, 'task_assigned');
```

### Validate Channels

```php
$validChannels = NotificationService::validateChannels($user, ['email', 'telegram', 'slack']);
```

---

## Real-World Snippets

### Task Assignment

```php
NotificationService::sendAsync(
    $assignee,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $assignee->name,
        'parameter2' => $task->name,
        'parameter3' => $task->project->name,
        'parameter4' => auth()->user()->name,
    ],
    ['email', 'database', 'telegram'],
    [
        'subject' => 'New Task: ' . $task->name,
        'action_url' => route('tasks.show', $task->id),
        'action_text' => 'View Task',
        'url' => route('tasks.show', $task->id)
    ]
);
```

### Task Completed

```php
NotificationService::sendAsync(
    $pic,
    'user_submit_their_task_with_image',
    [
        'parameter1' => $pic->name,
        'parameter2' => $task->name,
        'parameter3' => $task->project->name,
        'parameter4' => $worker->name,
        'images' => ['image1' => Storage::url($task->proof_of_work)]
    ],
    ['email', 'database', 'slack']
);
```

### Deadline Added

```php
NotificationService::sendAsync(
    $task->assignees,
    'deadline_has_been_added',
    [
        'parameter1' => '{user_name}',
        'parameter2' => $task->name,
        'parameter3' => $task->project->name,
        'parameter4' => $deadline->format('d M Y, H:i'),
    ],
    ['email', 'database', 'telegram']
);
```

---

## Configuration Quick Setup

### .env

```env
# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@example.com

# Telegram
TELEGRAM_ENDPOINT=http://telegram-service.example.com

# Queue
QUEUE_CONNECTION=redis
```

### User Model

```php
protected $fillable = [
    'telegram_chat_id',
    'slack_webhook_url',
    'slack_channel',
    'notification_preferences',
];

protected $casts = [
    'notification_preferences' => 'array',
];

public function routeNotificationForSlack($notification)
{
    return $this->slack_webhook_url;
}
```

---

## Debugging

### Check if template exists

```php
$template = DB::table('notification_settings')
    ->where('action', 'user_has_been_assigned_to_task')
    ->first();
```

### View user notifications

```php
$notifications = $user->notifications;
$unread = $user->unreadNotifications;
```

### Test notification

```bash
php artisan tinker
```

```php
$user = User::find(1);
NotificationService::send($user, 'user_has_been_assigned_to_task', [...], ['database']);
$user->notifications; // Check result
```

### View logs

```bash
tail -f storage/logs/laravel.log | grep "notification"
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Email not sending | Check MAIL config, queue worker, user email |
| Telegram fails | Check TELEGRAM_ENDPOINT, user telegram_chat_id |
| Queue not processing | Run `php artisan queue:work` |
| Template not found | Check notification_settings table |
| Job failing | Check logs: `storage/logs/laravel.log` |

---

## Commands

```bash
# Create notifications table
php artisan notifications:table
php artisan migrate

# Seed templates
php artisan db:seed --class=NotificationSettingsSeeder

# Start queue worker
php artisan queue:work

# Restart queue
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Available Notification Actions

1. `interactive_event_has_been_approved`
2. `project_deal_has_been_approved`
3. `deadline_has_been_added`
4. `user_has_been_assigned_to_task`
5. `user_has_been_removed_from_task`
6. `user_submit_their_task_with_image`
7. `pic_has_been_assigned_to_event`
8. `task_has_been_revise_by_pic`
9. `task_has_been_hold_by_user`

---

## Files Reference

- Service: `app/Services/NotificationService.php`
- Job: `app/Jobs/SendNotificationJob.php`
- Notifications: `app/Notifications/`
  - `GenericNotification.php` (Email)
  - `SlackNotification.php` (Slack)
  - `DatabaseNotification.php` (Database)
- Documentation: `NOTIFICATION_SERVICE_DOCUMENTATION.md`
- Quick Reference: This file

---

**Need help?** Check full documentation: `NOTIFICATION_SERVICE_DOCUMENTATION.md`
