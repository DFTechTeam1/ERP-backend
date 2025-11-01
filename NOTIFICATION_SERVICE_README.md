# 🔔 Unified Notification Service - README

## Overview

A comprehensive, production-ready notification service for Laravel that provides a **unified interface** to send notifications across **4 channels**: Email, Slack, Telegram, and Database.

### ✨ Key Features

- ✅ **Single Service** - One service handles all notification types
- ✅ **Multi-Channel** - Email, Slack, Telegram (microservice), Database
- ✅ **Template-Based** - Uses `notification_settings` table
- ✅ **Async Support** - Queue-based notifications
- ✅ **Error Handling** - Comprehensive logging and retry mechanism
- ✅ **User Preferences** - Respects user notification settings
- ✅ **Channel Validation** - Automatic validation before sending
- ✅ **Bulk Sending** - Send to multiple users at once

---

## 📁 Files Created

### Core Service
```
app/Services/NotificationService.php (630 lines)
```
Main service with methods:
- `send()` - Send synchronously
- `sendAsync()` - Send via queue
- `getUserChannels()` - Get user preferences
- `isChannelAvailable()` - Check channel availability
- `validateChannels()` - Filter valid channels

### Notification Classes
```
app/Notifications/
├── GenericNotification.php (Email)
├── SlackNotification.php (Slack)
└── DatabaseNotification.php (Database)
```

### Job Class
```
app/Jobs/SendNotificationJob.php
```
Handles async notification sending with retry mechanism (3 attempts).

### Documentation
```
NOTIFICATION_SERVICE_DOCUMENTATION.md (Complete guide - 1000+ lines)
NOTIFICATION_SERVICE_QUICK_REFERENCE.md (Quick lookup)
```

---

## 🚀 Quick Start

### 1. Installation

```bash
# Create notifications table
php artisan notifications:table
php artisan migrate

# Seed notification templates
php artisan db:seed --class=NotificationSettingsSeeder

# Start queue worker
php artisan queue:work
```

### 2. Basic Usage

```php
use App\Services\NotificationService;

// Send to database only
NotificationService::send(
    $user,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Design Homepage',
        'parameter3' => 'Website Project',
        'parameter4' => 'Manager',
    ],
    ['database']
);

// Send to multiple channels (async - recommended)
NotificationService::sendAsync(
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

---

## 📊 Supported Channels

| Channel | Laravel Method | Requirements | Notes |
|---------|---------------|--------------|-------|
| **Email** | `->notify()` | User has email | Uses Laravel Mail |
| **Slack** | `->notify()` | User has slack_webhook_url | Uses Laravel Slack |
| **Telegram** | HTTP Client | User has telegram_chat_id | Calls microservice |
| **Database** | `->notify()` | None (always available) | Laravel DB notifications |

---

## 🎯 Channel Details

### Email
Uses Laravel's built-in notification system.
```php
NotificationService::send($user, 'action', $data, ['email'], [
    'subject' => 'Custom Subject',
    'action_url' => route('tasks.show', $id),
    'action_text' => 'View Task'
]);
```

### Slack
Uses Laravel's Slack notification.
```php
NotificationService::send($user, 'action', $data, ['slack'], [
    'attachment' => [
        'title' => 'Details',
        'fields' => ['Project' => 'Name'],
        'color' => '#28a745'
    ]
]);
```

### Telegram (Microservice)
Sends HTTP request to your Telegram microservice.
```php
NotificationService::send($user, 'action', $data, ['telegram'], [
    'parse_mode' => 'HTML',
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => 'View', 'url' => $url]
        ]]
    ]
]);
```

**Microservice Endpoint**: `POST /send-notification`

**Payload**:
```json
{
  "chat_id": "123456789",
  "message": "Notification message",
  "action": "user_has_been_assigned_to_task",
  "data": {...},
  "parse_mode": "HTML"
}
```

### Database
Stores in Laravel's `notifications` table.
```php
NotificationService::send($user, 'action', $data, ['database'], [
    'title' => 'Custom Title',
    'icon' => '🔔',
    'url' => route('tasks.show', $id)
]);

// Retrieve
$user->notifications;
$user->unreadNotifications;
$notification->markAsRead();
```

---

## 💻 Real-World Examples

### Task Assignment
```php
public function assignTask($taskId, $userId)
{
    $task = Task::find($taskId);
    $user = User::find($userId);
    
    // Business logic...
    $task->assignee_id = $userId;
    $task->save();
    
    // Send notification
    NotificationService::sendAsync(
        $user,
        'user_has_been_assigned_to_task',
        [
            'parameter1' => $user->name,
            'parameter2' => $task->name,
            'parameter3' => $task->project->name,
            'parameter4' => auth()->user()->name,
        ],
        ['email', 'database', 'telegram'],
        [
            'subject' => 'New Task: ' . $task->name,
            'action_url' => route('tasks.show', $task->id),
            'action_text' => 'View Task Details',
            'url' => route('tasks.show', $task->id)
        ]
    );
}
```

### Task Completed with Proof
```php
public function submitTask($taskId, $request)
{
    $task = Task::find($taskId);
    $pic = $task->project->pic;
    $imagePath = $request->file('proof')->store('proofs');
    
    // Business logic...
    $task->status = 'submitted';
    $task->save();
    
    // Notify PIC
    NotificationService::sendAsync(
        $pic,
        'user_submit_their_task_with_image',
        [
            'parameter1' => $pic->name,
            'parameter2' => $task->name,
            'parameter3' => $task->project->name,
            'parameter4' => auth()->user()->name,
            'images' => ['image1' => Storage::url($imagePath)]
        ],
        ['email', 'database', 'slack']
    );
}
```

### Bulk Notification
```php
public function notifyTeam($projectId)
{
    $project = Project::find($projectId);
    $team = $project->team;
    
    NotificationService::sendAsync(
        $team,
        'project_deal_has_been_approved',
        [
            'parameter1' => '{user_name}', // Auto-replaced per user
            'parameter2' => $project->name,
        ],
        ['email', 'database']
    );
}
```

---

## ⚙️ Configuration

### 1. Environment Variables

```env
# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@example.com

# Telegram Microservice
TELEGRAM_ENDPOINT=http://telegram-service.example.com

# Slack
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Queue
QUEUE_CONNECTION=redis
```

### 2. User Model

Add these fields to users table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->json('notification_preferences')->nullable();
    $table->string('telegram_chat_id')->nullable();
    $table->string('slack_webhook_url')->nullable();
    $table->string('slack_channel')->nullable();
});
```

Update User model:

```php
class User extends Authenticatable
{
    use Notifiable;
    
    protected $fillable = [
        'telegram_chat_id',
        'slack_webhook_url',
        'notification_preferences',
    ];
    
    protected $casts = [
        'notification_preferences' => 'array',
    ];
    
    public function routeNotificationForSlack($notification)
    {
        return $this->slack_webhook_url;
    }
}
```

### 3. Queue Configuration

Run queue worker:

```bash
php artisan queue:work --queue=default,notifications
```

Or use Supervisor (recommended for production).

---

## 📋 Available Notification Templates

| Action | Parameters | Description |
|--------|------------|-------------|
| `interactive_event_has_been_approved` | 2 | Event disetujui |
| `project_deal_has_been_approved` | 2 | Project deal disetujui |
| `deadline_has_been_added` | 4 | Deadline baru |
| `user_has_been_assigned_to_task` | 4 | User ditugaskan |
| `user_has_been_removed_from_task` | 3 | User dihapus dari task |
| `user_submit_their_task_with_image` | 4 + image | Task selesai |
| `pic_has_been_assigned_to_event` | 2 | PIC ditunjuk |
| `task_has_been_revise_by_pic` | 5 | Task perlu revisi |
| `task_has_been_hold_by_user` | 5 | Task di-hold |

---

## 🧪 Testing

### Manual Test

```bash
php artisan tinker
```

```php
$user = User::find(1);

NotificationService::send(
    $user,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Test Task',
        'parameter3' => 'Test Project',
        'parameter4' => 'Manager',
    ],
    ['database']
);

// Check result
$user->notifications;
```

### Unit Test

```php
public function test_send_notification()
{
    $user = User::factory()->create();
    
    $results = NotificationService::send(
        $user,
        'user_has_been_assigned_to_task',
        [
            'parameter1' => $user->name,
            'parameter2' => 'Task',
            'parameter3' => 'Project',
            'parameter4' => 'Manager',
        ],
        ['database']
    );
    
    $this->assertTrue($results[0]['channels']['database']['success']);
}
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Email not sending | Check MAIL config, queue worker running, user has email |
| Telegram fails | Check TELEGRAM_ENDPOINT, user has telegram_chat_id, microservice is up |
| Queue not processing | Run `php artisan queue:work` |
| Template not found | Check notification_settings table has the action |
| Job keeps failing | Check `storage/logs/laravel.log` for errors |

### Commands

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# View logs
tail -f storage/logs/laravel.log | grep "notification"

# Clear cache
php artisan config:clear
php artisan cache:clear
```

---

## 📚 Documentation

- **Full Guide**: `NOTIFICATION_SERVICE_DOCUMENTATION.md` (1000+ lines)
- **Quick Reference**: `NOTIFICATION_SERVICE_QUICK_REFERENCE.md`
- **Templates Guide**: `NOTIFICATION_SETTINGS_DOCUMENTATION.md`
- **This README**: Overview and quick start

---

## 🔐 Best Practices

1. ✅ **Use `sendAsync()` for production** - Always queue notifications
2. ✅ **Validate channels** - Check user has required fields
3. ✅ **Handle failures gracefully** - Don't break main flow if notification fails
4. ✅ **Log everything** - Monitor notification activities
5. ✅ **Test each channel** - Test individually before combining
6. ✅ **Respect preferences** - Check user notification settings
7. ✅ **Use templates** - Don't hardcode messages
8. ✅ **Provide fallback** - Always send to database as minimum
9. ✅ **Monitor queue** - Ensure queue worker is always running
10. ✅ **Rate limiting** - Be careful with bulk notifications

---

## 📊 Architecture

```
┌─────────────────────────────────────────────────────┐
│              NotificationService                     │
│  ┌──────────────────────────────────────────────┐  │
│  │  send() / sendAsync()                        │  │
│  └──────────────────────────────────────────────┘  │
│                      │                              │
│         ┌────────────┼────────────┬────────────┐   │
│         ▼            ▼            ▼            ▼   │
│     ┌──────┐   ┌──────┐   ┌──────────┐  ┌──────┐ │
│     │Email │   │Slack │   │Telegram  │  │ DB   │ │
│     │      │   │      │   │(HTTP)    │  │      │ │
│     └──────┘   └──────┘   └──────────┘  └──────┘ │
│         │           │            │           │     │
│         ▼           ▼            ▼           ▼     │
│     Laravel     Laravel    Microservice  Laravel   │
│     Mail        Slack                    Database  │
└─────────────────────────────────────────────────────┘
```

---

## 🎉 Usage Summary

```php
// ✅ Simple (Database only)
NotificationService::send($user, 'action', $data, ['database']);

// ✅ Multiple channels
NotificationService::send($user, 'action', $data, ['email', 'database', 'telegram']);

// ✅ Async (Recommended for production)
NotificationService::sendAsync($user, 'action', $data, ['email', 'database']);

// ✅ Multiple users
NotificationService::sendAsync($users, 'action', $data, ['email', 'database']);

// ✅ With options
NotificationService::sendAsync($user, 'action', $data, ['email'], [
    'subject' => 'Custom Subject',
    'action_url' => route('resource.show', $id)
]);
```

---

## ✅ Checklist

Before using in production:

- [ ] Run migrations (`php artisan notifications:table && migrate`)
- [ ] Seed templates (`php artisan db:seed --class=NotificationSettingsSeeder`)
- [ ] Configure `.env` (MAIL, TELEGRAM_ENDPOINT, etc.)
- [ ] Add user fields (telegram_chat_id, etc.)
- [ ] Start queue worker (`php artisan queue:work`)
- [ ] Test each channel individually
- [ ] Set up Supervisor for queue
- [ ] Configure logging/monitoring
- [ ] Test error scenarios
- [ ] Document your notification actions

---

**Version**: 1.0  
**Created**: 1 November 2025  
**Maintained by**: ERP Development Team  

**Questions?** Check `NOTIFICATION_SERVICE_DOCUMENTATION.md` for detailed guide.
