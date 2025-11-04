# 🔔 Unified Notification Service - Complete Guide

## Overview

The **NotificationService** is a centralized service for handling all notifications across the ERP system. It provides a unified interface to send notifications through multiple channels:

- 📧 **Email** - via Laravel Mail/Notification
- 💬 **Slack** - via Laravel Slack Notification
- 📱 **Telegram** - via HTTP microservice
- 💾 **Database** - via Laravel Database Notification

## Key Features

✅ **Unified Interface** - Single method to send to all channels  
✅ **Multi-Channel Support** - Send to one or multiple channels simultaneously  
✅ **Template-Based** - Uses notification_settings table for message templates  
✅ **Asynchronous Support** - Send notifications via queue  
✅ **Error Handling** - Comprehensive error logging and recovery  
✅ **User Preferences** - Respects user notification preferences  
✅ **Channel Validation** - Checks if channel is available for user  

---

## 📁 Files Structure

```
app/
├── Services/
│   └── NotificationService.php          # Main service class
├── Notifications/
│   ├── GenericNotification.php          # Email notification
│   ├── SlackNotification.php            # Slack notification
│   └── DatabaseNotification.php         # Database notification
└── Jobs/
    └── SendNotificationJob.php          # Async job for notifications
```

---

## 🚀 Quick Start

### Basic Usage - Single Channel

```php
use App\Services\NotificationService;
use App\Models\User;

$user = User::find(1);

// Send database notification only
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
```

### Multiple Channels

```php
// Send to email, database, and telegram
NotificationService::send(
    $user,
    'deadline_has_been_added',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Design Homepage',
        'parameter3' => 'Website Project',
        'parameter4' => '31 Oct 2025, 17:00',
    ],
    ['email', 'database', 'telegram']
);
```

### Multiple Recipients

```php
$users = User::whereIn('id', [1, 2, 3])->get();

NotificationService::send(
    $users,
    'project_deal_has_been_approved',
    [
        'parameter1' => '{user_name}', // Will be replaced per user
        'parameter2' => 'Website Redesign Project',
    ],
    ['email', 'database']
);
```

### Asynchronous (Queue)

```php
// Send notification via queue
NotificationService::sendAsync(
    $user,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Backend API Development',
        'parameter3' => 'Mobile App Project',
        'parameter4' => 'Project Manager',
    ],
    ['email', 'slack', 'database', 'telegram']
);
```

---

## 📝 Available Methods

### 1. `send()`

Send notification immediately (synchronous).

```php
NotificationService::send(
    $recipients,    // User|array - Single user or array of users
    $action,        // string - Action from notification_settings
    $data,          // array - Template parameters
    $channels,      // array - Channels to send
    $options        // array - Additional options
): array;          // Returns results array
```

**Returns:**
```php
[
    [
        'recipient' => 'user@example.com',
        'action' => 'user_has_been_assigned_to_task',
        'channels' => [
            'email' => [
                'success' => true,
                'channel' => 'email',
                'sent_at' => '2025-11-01 10:30:00'
            ],
            'database' => [
                'success' => true,
                'channel' => 'database',
                'saved_at' => '2025-11-01 10:30:00'
            ],
            'telegram' => [
                'success' => false,
                'channel' => 'telegram',
                'error' => 'Telegram chat ID not found'
            ]
        ]
    ]
]
```

### 2. `sendAsync()`

Send notification asynchronously via queue.

```php
NotificationService::sendAsync(
    $recipients,    // User|array
    $action,        // string
    $data,          // array
    $channels,      // array
    $options        // array
): void;
```

### 3. `getUserChannels()`

Get user's preferred channels for specific action.

```php
$channels = NotificationService::getUserChannels($user, 'task_assigned');
// Returns: ['email', 'database', 'telegram']
```

### 4. `isChannelAvailable()`

Check if channel is available for user.

```php
$canEmail = NotificationService::isChannelAvailable($user, 'email');
// Returns: true/false
```

### 5. `validateChannels()`

Filter channels based on user availability.

```php
$requestedChannels = ['email', 'telegram', 'slack'];
$validChannels = NotificationService::validateChannels($user, $requestedChannels);
// Returns: ['email', 'telegram'] (if slack not configured for user)
```

---

## 📊 Channel Details

### 1. Email Channel

**Requirements:**
- User must have valid email address
- MAIL configuration in `.env`

**Options:**
```php
$options = [
    'subject' => 'Custom Subject',
    'greeting' => 'Hello John!',
    'salutation' => 'Best regards, Team',
    'action_url' => 'https://app.example.com/tasks/123',
    'action_text' => 'View Task',
    'additional_lines' => [
        'Please complete this by end of day.',
        'Contact support if you need help.'
    ]
];
```

**Example:**
```php
NotificationService::send(
    $user,
    'user_has_been_assigned_to_task',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Design Homepage',
        'parameter3' => 'Website Project',
        'parameter4' => 'Manager',
    ],
    ['email'],
    [
        'subject' => 'New Task Assigned to You',
        'action_url' => route('tasks.show', 123),
        'action_text' => 'View Task Details'
    ]
);
```

---

### 2. Slack Channel

**Requirements:**
- User must have `slack_webhook_url` or `slack_channel` configured
- Slack app configured in Laravel

**Options:**
```php
$options = [
    'from' => 'ERP System',
    'icon' => ':robot_face:',
    'attachment' => [
        'title' => 'Task Details',
        'fields' => [
            'Project' => 'Website Redesign',
            'Deadline' => '31 Oct 2025',
            'Priority' => 'High'
        ],
        'color' => '#28a745' // green, red: #dc3545, blue: #007bff
    ]
];
```

**Example:**
```php
NotificationService::send(
    $user,
    'deadline_has_been_added',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Backend API',
        'parameter3' => 'Mobile App',
        'parameter4' => '31 Oct 2025, 17:00',
    ],
    ['slack'],
    [
        'attachment' => [
            'title' => 'Deadline Information',
            'fields' => [
                'Task' => 'Backend API',
                'Project' => 'Mobile App',
                'Due' => '31 Oct 2025, 17:00'
            ],
            'color' => '#dc3545'
        ]
    ]
);
```

---

### 3. Telegram Channel

**Requirements:**
- User must have `telegram_chat_id` in database
- Telegram microservice endpoint configured
- `TELEGRAM_ENDPOINT` in `.env` or `services.telegram.endpoint` in config

**Configuration:**

`.env`:
```env
TELEGRAM_ENDPOINT=http://telegram-service.example.com
```

Or in `config/services.php`:
```php
'telegram' => [
    'endpoint' => env('TELEGRAM_ENDPOINT', 'http://localhost:3000'),
],
```

**Microservice Payload:**
```json
{
  "chat_id": "123456789",
  "message": "Hi John, kamu ditugaskan ke task Design Homepage...",
  "action": "user_has_been_assigned_to_task",
  "data": {
    "parameter1": "John",
    "parameter2": "Design Homepage",
    "parameter3": "Website Project"
  },
  "parse_mode": "HTML",
  "reply_markup": {
    "inline_keyboard": [[
      {"text": "View Task", "url": "https://app.example.com/tasks/123"}
    ]]
  }
}
```

**Options:**
```php
$options = [
    'parse_mode' => 'HTML', // or 'Markdown'
    'reply_markup' => [
        'inline_keyboard' => [[
            ['text' => 'View Task', 'url' => 'https://app.example.com/tasks/123'],
            ['text' => 'Mark as Read', 'callback_data' => 'mark_read']
        ]]
    ]
];
```

**Example:**
```php
NotificationService::send(
    $user,
    'task_has_been_revise_by_pic',
    [
        'parameter1' => $user->name,
        'parameter2' => 'UI Design',
        'parameter3' => 'Mobile App',
        'parameter4' => 'Lead Designer',
        'parameter5' => 'Please adjust colors to match brand guidelines'
    ],
    ['telegram'],
    [
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [[
                ['text' => 'View Task', 'url' => route('tasks.show', 123)],
                ['text' => 'Reply', 'callback_data' => 'reply_123']
            ]]
        ]
    ]
);
```

---

### 4. Database Channel

**Requirements:**
- Laravel notifications table migrated
- Always available (no user configuration needed)

**Options:**
```php
$options = [
    'title' => 'Custom Title',
    'icon' => '🔔',
    'url' => 'https://app.example.com/tasks/123'
];
```

**Database Structure:**
```json
{
  "id": "uuid",
  "type": "App\\Notifications\\DatabaseNotification",
  "notifiable_type": "App\\Models\\User",
  "notifiable_id": 1,
  "data": {
    "action": "user_has_been_assigned_to_task",
    "message": "Hi John, kamu ditugaskan ke task Design Homepage...",
    "title": "New Task Assignment",
    "icon": "📋",
    "url": "/tasks/123",
    "data": {
      "parameter1": "John",
      "parameter2": "Design Homepage"
    },
    "read": false,
    "created_at": "2025-11-01 10:30:00"
  },
  "read_at": null,
  "created_at": "2025-11-01 10:30:00",
  "updated_at": "2025-11-01 10:30:00"
}
```

**Retrieve Notifications:**
```php
// Get unread notifications
$notifications = $user->unreadNotifications;

// Get all notifications
$notifications = $user->notifications;

// Mark as read
$notification->markAsRead();

// Mark all as read
$user->unreadNotifications->markAsRead();
```

---

## 🎯 Real-World Examples

### Example 1: Task Assignment

```php
use App\Services\NotificationService;

// In your TaskController or Service
public function assignTask($taskId, $userId)
{
    $task = Task::find($taskId);
    $user = User::find($userId);
    $assigner = auth()->user();
    
    // Assign task logic...
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
            'parameter4' => $assigner->name,
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

### Example 2: Task Submission with Image

```php
public function submitTask($taskId, $request)
{
    $task = Task::find($taskId);
    $worker = auth()->user();
    $pic = $task->project->pic;
    
    // Save proof of work
    $imagePath = $request->file('proof')->store('proofs');
    $imageUrl = Storage::url($imagePath);
    
    $task->status = 'submitted';
    $task->proof_of_work = $imagePath;
    $task->save();
    
    // Notify PIC
    NotificationService::sendAsync(
        $pic,
        'user_submit_their_task_with_image',
        [
            'parameter1' => $pic->name,
            'parameter2' => $task->name,
            'parameter3' => $task->project->name,
            'parameter4' => $worker->name,
            'images' => [
                'image1' => $imageUrl
            ]
        ],
        ['email', 'database', 'slack'],
        [
            'subject' => 'Task Completed: ' . $task->name,
            'action_url' => route('tasks.review', $task->id),
            'action_text' => 'Review Task'
        ]
    );
}
```

### Example 3: Deadline Added

```php
public function addDeadline($taskId, $deadline)
{
    $task = Task::find($taskId);
    $task->deadline = $deadline;
    $task->save();
    
    // Notify all assignees
    $assignees = $task->assignees;
    
    NotificationService::sendAsync(
        $assignees,
        'deadline_has_been_added',
        [
            'parameter1' => '{user_name}', // Will be replaced per user
            'parameter2' => $task->name,
            'parameter3' => $task->project->name,
            'parameter4' => $deadline->format('d M Y, H:i'),
        ],
        ['email', 'database', 'telegram'],
        [
            'subject' => 'Deadline Added: ' . $task->name,
            'url' => route('tasks.show', $task->id)
        ]
    );
}
```

### Example 4: Task Revision

```php
public function reviseTask($taskId, $revisionNotes)
{
    $task = Task::find($taskId);
    $pic = auth()->user();
    $worker = $task->assignee;
    
    $task->status = 'revision';
    $task->revision_notes = $revisionNotes;
    $task->save();
    
    // Notify worker
    NotificationService::sendAsync(
        $worker,
        'task_has_been_revise_by_pic',
        [
            'parameter1' => $worker->name,
            'parameter2' => $task->name,
            'parameter3' => $task->project->name,
            'parameter4' => $pic->name,
            'parameter5' => $revisionNotes,
        ],
        ['email', 'database', 'telegram', 'slack'],
        [
            'subject' => 'Task Revision Required: ' . $task->name,
            'action_url' => route('tasks.show', $task->id),
            'action_text' => 'View Revision Notes',
            'url' => route('tasks.show', $task->id)
        ]
    );
}
```

---

## 🔧 Configuration

### 1. Database Migration

Ensure notifications table exists:

```bash
php artisan notifications:table
php artisan migrate
```

### 2. User Model

Add notification preferences field:

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->json('notification_preferences')->nullable();
    $table->string('telegram_chat_id')->nullable();
    $table->string('slack_webhook_url')->nullable();
    $table->string('slack_channel')->nullable();
});
```

### 3. User Model Methods

```php
// app/Models/User.php
class User extends Authenticatable
{
    use Notifiable;
    
    protected $casts = [
        'notification_preferences' => 'array',
    ];
    
    /**
     * Route notification for Slack
     */
    public function routeNotificationForSlack($notification)
    {
        return $this->slack_webhook_url;
    }
}
```

### 4. Environment Variables

```env
# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# Telegram Microservice
TELEGRAM_ENDPOINT=http://telegram-service.example.com

# Slack (if using webhook)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Queue
QUEUE_CONNECTION=redis
```

### 5. Queue Configuration

Make sure queue is running:

```bash
php artisan queue:work --queue=default,notifications
```

Or use Supervisor:

```ini
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

---

## 🧪 Testing

### Unit Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;

class NotificationServiceTest extends TestCase
{
    public function test_send_database_notification()
    {
        $user = User::factory()->create();
        
        $results = NotificationService::send(
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
        
        $this->assertTrue($results[0]['channels']['database']['success']);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
        ]);
    }
    
    public function test_send_email_notification()
    {
        Notification::fake();
        
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        NotificationService::send(
            $user,
            'user_has_been_assigned_to_task',
            [
                'parameter1' => $user->name,
                'parameter2' => 'Test Task',
                'parameter3' => 'Test Project',
                'parameter4' => 'Manager',
            ],
            ['email']
        );
        
        Notification::assertSentTo(
            $user,
            \App\Notifications\GenericNotification::class
        );
    }
}
```

### Manual Testing

```bash
php artisan tinker
```

```php
$user = User::find(1);

// Test database notification
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

// Test multiple channels
NotificationService::send(
    $user,
    'deadline_has_been_added',
    [
        'parameter1' => $user->name,
        'parameter2' => 'Design Homepage',
        'parameter3' => 'Website Project',
        'parameter4' => '31 Oct 2025, 17:00',
    ],
    ['email', 'database']
);
```

---

## 📊 Monitoring & Logging

All notification activities are logged:

```bash
# View logs
tail -f storage/logs/laravel.log | grep "notification"
```

**Log Entries:**

```log
[2025-11-01 10:30:00] Email notification sent {"recipient":"user@example.com","action":"user_has_been_assigned_to_task"}
[2025-11-01 10:30:01] Database notification saved {"recipient":1,"action":"user_has_been_assigned_to_task"}
[2025-11-01 10:30:02] Telegram notification sent {"recipient":1,"chat_id":"123456789","action":"user_has_been_assigned_to_task"}
```

---

## 🐛 Troubleshooting

### Issue: Email not sending

**Check:**
1. MAIL configuration in `.env`
2. Queue is running
3. User has valid email address

```bash
php artisan config:clear
php artisan queue:restart
```

### Issue: Telegram not working

**Check:**
1. `TELEGRAM_ENDPOINT` is configured
2. User has `telegram_chat_id`
3. Microservice is running

```php
// Test telegram endpoint
Http::post(config('app.telegram_endpoint') . '/health');
```

### Issue: Notifications not queued

**Check:**
1. Queue connection is configured
2. Queue worker is running

```bash
php artisan queue:work --tries=3
```

### Issue: Template not found

**Check:**
1. Template exists in `notification_settings` table
2. Action name matches exactly

```sql
SELECT * FROM notification_settings WHERE action = 'your_action_name';
```

---

## 🔐 Best Practices

1. **Always use queue for notifications** - Use `sendAsync()` instead of `send()`
2. **Validate user preferences** - Check user settings before sending
3. **Handle failures gracefully** - Log errors but don't break main flow
4. **Use descriptive action names** - Follow naming convention: `entity_action_by_actor`
5. **Keep templates up to date** - Update notification_settings when adding new notifications
6. **Test before production** - Test each channel individually
7. **Monitor logs** - Regularly check notification logs
8. **Rate limiting** - Be careful with bulk notifications
9. **Respect user preferences** - Allow users to disable channels
10. **Fallback mechanism** - Always send to database as fallback

---

## 📚 Additional Resources

- [Laravel Notifications Documentation](https://laravel.com/docs/notifications)
- [Laravel Queues Documentation](https://laravel.com/docs/queues)
- [Slack API Documentation](https://api.slack.com/)
- [Telegram Bot API](https://core.telegram.org/bots/api)

---

## 🤝 Contributing

When adding new notification types:

1. Add template to `notification_settings` table
2. Update this documentation
3. Add example usage
4. Write tests
5. Update CHANGELOG

---

**Created**: 1 November 2025  
**Version**: 1.0  
**Last Updated**: 1 November 2025  
**Maintained by**: ERP Development Team
