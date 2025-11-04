# 📝 Notification Template Placeholders Guide

## Overview

This guide explains how to use placeholders in your `notification_settings` table templates.

---

## Available Placeholders

### 1. Text Parameters
**Format**: `<parameter1>`, `<parameter2>`, ..., `<parameterN>`

**Usage**: Replace with dynamic text values

**Example Template**:
```
Hi <parameter1>, kamu ditugaskan untuk task <parameter2> di project <parameter3> oleh <parameter4>.
```

**Data to Send**:
```php
NotificationService::send($user, 'action', [
    'parameter1' => 'John Doe',
    'parameter2' => 'Design Homepage',
    'parameter3' => 'Website Redesign',
    'parameter4' => 'Manager',
]);
```

**Result**:
```
Hi John Doe, kamu ditugaskan untuk task Design Homepage di project Website Redesign oleh Manager.
```

---

### 2. Images
**Format**: `<image1>`, `<image2>`, ..., `<imageN>`

**Usage**: Display images in notifications

**Example Template**:
```
Task screenshot: <image1>
Reference design: <image2>
```

**Data to Send**:
```php
NotificationService::send($user, 'action', [
    'parameter1' => 'User Name',
    'images' => [
        'image1' => 'https://example.com/screenshot.png',
        'image2' => 'https://example.com/reference.jpg',
    ]
]);
```

**Result**:
- **Email/HTML**: Renders as `<img>` tags
- **Telegram/Text**: Shows URL only
- **Database**: Stores URL

---

### 3. Audio Files
**Format**: `<audio1>`, `<audio2>`, ..., `<audioN>`

**Usage**: Include audio files in notifications

**Example Template**:
```
Voice message from manager: <audio1>
Team meeting recording: <audio2>
```

**Data to Send**:
```php
NotificationService::send($user, 'action', [
    'parameter1' => 'Meeting Notes',
    'audios' => [
        'audio1' => 'https://example.com/voice-message.mp3',
        'audio2' => 'https://example.com/meeting.mp3',
    ]
]);
```

**Result**:
- **Email/HTML**: Renders as `<audio>` player
- **Telegram/Text**: Shows URL
- **Database**: Stores URL

---

### 4. Documents
**Format**: `<document1>`, `<document2>`, ..., `<documentN>`

**Usage**: Attach documents/files

**Example Template**:
```
Project brief: <document1>
Requirements document: <document2>
```

**Data to Send**:
```php
NotificationService::send($user, 'action', [
    'parameter1' => 'Project Name',
    'documents' => [
        'document1' => 'https://example.com/brief.pdf',
        'document2' => 'https://example.com/requirements.docx',
    ]
]);
```

**Result**:
- **Email/HTML**: Renders as download link with 📄 icon
- **Telegram/Text**: Shows URL
- **Database**: Stores URL

---

### 5. New Line (Bubble)
**Format**: `<bubble>`

**Usage**: Add line breaks in templates

**Example Template**:
```
Hi <parameter1>,<bubble><bubble>Kamu ditugaskan untuk task <parameter2>.<bubble><bubble>Terima kasih!
```

**Result**:
```
Hi John Doe,

Kamu ditugaskan untuk task Design Homepage.

Terima kasih!
```

---

## Complete Example

### Template in Database

**Table**: `notification_settings`

**Action**: `user_has_been_assigned_to_task`

**Template (text)**:
```
🎯 Task Assignment<bubble><bubble>Hi <parameter1>,<bubble><bubble>Kamu ditugaskan untuk task "<parameter2>" di project <parameter3> oleh <parameter4>.<bubble><bubble>Screenshot: <image1><bubble>Document: <document1><bubble><bubble>Terima kasih! 🙏
```

**Template HTML**:
```html
<h2>🎯 Task Assignment</h2><bubble><bubble><p>Hi <parameter1>,</p><bubble><bubble><p>Kamu ditugaskan untuk task "<strong><parameter2></strong>" di project <parameter3> oleh <parameter4>.</p><bubble><bubble><p>Screenshot: <image1></p><bubble><p>Document: <document1></p><bubble><bubble><p>Terima kasih! 🙏</p>
```

---

### Code to Send

```php
use App\Services\NotificationService;

$user = User::find(1);

NotificationService::sendAsync(
    $user,
    'user_has_been_assigned_to_task',
    [
        // Text parameters
        'parameter1' => $user->name,
        'parameter2' => 'Design Homepage',
        'parameter3' => 'Website Redesign Project',
        'parameter4' => auth()->user()->name,
        
        // Images
        'images' => [
            'image1' => 'https://example.com/task-screenshot.png',
        ],
        
        // Documents
        'documents' => [
            'document1' => 'https://example.com/project-brief.pdf',
        ],
    ],
    ['email', 'database', 'telegram'],
    [
        'subject' => 'New Task Assignment',
        'action_url' => route('tasks.show', 123),
        'action_text' => 'View Task',
    ]
);
```

---

### Output by Channel

#### 📧 Email (HTML)
```html
<h2>🎯 Task Assignment</h2>
<br><br>
<p>Hi John Doe,</p>
<br><br>
<p>Kamu ditugaskan untuk task "<strong>Design Homepage</strong>" di project Website Redesign Project oleh Manager Name.</p>
<br><br>
<p>Screenshot: <img src="https://example.com/task-screenshot.png" alt="image1" style="max-width: 100%; height: auto;"></p>
<br>
<p>Document: <a href="https://example.com/project-brief.pdf" target="_blank" download>📄 project-brief.pdf</a></p>
<br><br>
<p>Terima kasih! 🙏</p>
```

#### 💬 Telegram (Text)
```
🎯 Task Assignment

Hi John Doe,

Kamu ditugaskan untuk task "Design Homepage" di project Website Redesign Project oleh Manager Name.

Screenshot: https://example.com/task-screenshot.png
Document: https://example.com/project-brief.pdf

Terima kasih! 🙏
```

#### 💾 Database (JSON)
```json
{
  "action": "user_has_been_assigned_to_task",
  "message": "🎯 Task Assignment\n\nHi John Doe,\n\nKamu ditugaskan untuk task...",
  "data": {
    "parameter1": "John Doe",
    "parameter2": "Design Homepage",
    "images": {
      "image1": "https://example.com/task-screenshot.png"
    },
    "documents": {
      "document1": "https://example.com/project-brief.pdf"
    }
  }
}
```

---

## Data Structure

### Simple Text Only

```php
$data = [
    'parameter1' => 'Value 1',
    'parameter2' => 'Value 2',
    'parameter3' => 'Value 3',
];
```

### With Images

```php
$data = [
    'parameter1' => 'Task Name',
    'parameter2' => 'Project Name',
    'images' => [
        'image1' => 'https://cdn.example.com/image1.png',
        'image2' => 'https://cdn.example.com/image2.jpg',
    ]
];
```

### With Audio

```php
$data = [
    'parameter1' => 'Meeting Notes',
    'audios' => [
        'audio1' => 'https://cdn.example.com/recording.mp3',
    ]
];
```

### With Documents

```php
$data = [
    'parameter1' => 'Project Requirements',
    'documents' => [
        'document1' => 'https://cdn.example.com/requirements.pdf',
        'document2' => 'https://cdn.example.com/specs.docx',
    ]
];
```

### Complete Example (All Types)

```php
$data = [
    // Text parameters
    'parameter1' => 'John Doe',
    'parameter2' => 'Design Task',
    'parameter3' => 'Website Project',
    'parameter4' => 'Manager',
    
    // Media files
    'images' => [
        'image1' => 'https://cdn.example.com/screenshot.png',
        'image2' => 'https://cdn.example.com/reference.jpg',
    ],
    'audios' => [
        'audio1' => 'https://cdn.example.com/briefing.mp3',
    ],
    'documents' => [
        'document1' => 'https://cdn.example.com/brief.pdf',
        'document2' => 'https://cdn.example.com/requirements.docx',
    ]
];
```

---

## Best Practices

### 1. Use Sequential Numbering
✅ **Good**: `parameter1`, `parameter2`, `parameter3`
❌ **Bad**: `parameter1`, `parameter5`, `parameter10` (skip numbers)

### 2. Always Provide All Parameters
```php
// If template has parameter1 to parameter4, provide all:
$data = [
    'parameter1' => 'Value 1',
    'parameter2' => 'Value 2',
    'parameter3' => 'Value 3',
    'parameter4' => 'Value 4',
];
```

### 3. Use Descriptive Variable Names in Code
```php
// Good - Clear what each parameter represents
$data = [
    'parameter1' => $user->name,        // User name
    'parameter2' => $task->title,       // Task title
    'parameter3' => $project->name,     // Project name
    'parameter4' => $assignedBy->name,  // Assigned by
];
```

### 4. Store Media URLs, Not Local Paths
```php
// Good - Public accessible URL
'image1' => 'https://cdn.example.com/image.png'

// Bad - Local path (won't work in email/telegram)
'image1' => '/storage/images/image.png'
```

### 5. Test with All Channels
```php
// Test with all channels to ensure consistent rendering
NotificationService::send(
    $testUser,
    'test_action',
    $testData,
    ['email', 'slack', 'telegram', 'database']
);
```

---

## Troubleshooting

### Placeholder Not Replaced

**Problem**: Template shows `<parameter1>` instead of value

**Solutions**:
1. Check data array has the key: `$data['parameter1']`
2. Ensure key matches exactly (case-sensitive)
3. Verify template in database has correct placeholder format

### Image Not Showing

**Problem**: Image doesn't display in email

**Solutions**:
1. Verify URL is publicly accessible
2. Check URL is absolute (https://...)
3. Ensure images array is properly structured:
   ```php
   'images' => [
       'image1' => 'https://...',  // ✅ Correct
   ]
   // NOT:
   'image1' => 'https://...'       // ❌ Wrong
   ```

### New Lines Not Working

**Problem**: `<bubble>` shows as text

**Solutions**:
1. Ensure template is saved correctly in database
2. Check no extra spaces: `<bubble>` not `< bubble >`
3. Verify NotificationService is updated

### Audio/Document Not Accessible

**Problem**: Files can't be downloaded

**Solutions**:
1. Use CDN or public storage
2. Set correct permissions on storage
3. Use signed URLs for private files:
   ```php
   'document1' => Storage::temporaryUrl('file.pdf', now()->addHours(24))
   ```

---

## Migration Guide (Old → New)

If you have old templates using `<newLine>`, update them:

### Old Format
```
Hi <parameter1>.<newLine><newLine>Your task: <parameter2>
```

### New Format
```
Hi <parameter1>.<bubble><bubble>Your task: <parameter2>
```

### Update SQL
```sql
UPDATE notification_settings 
SET template = REPLACE(template, '<newLine>', '<bubble>'),
    template_html = REPLACE(template_html, '<newLine>', '<bubble>')
WHERE template LIKE '%<newLine>%';
```

---

## Examples by Use Case

### 1. Task Assignment
```php
$data = [
    'parameter1' => $user->name,
    'parameter2' => $task->title,
    'parameter3' => $project->name,
    'parameter4' => auth()->user()->name,
];
```

### 2. Deadline Reminder with Document
```php
$data = [
    'parameter1' => $user->name,
    'parameter2' => $task->title,
    'parameter3' => $deadline,
    'documents' => [
        'document1' => $task->requirements_file_url,
    ]
];
```

### 3. Design Review with Images
```php
$data = [
    'parameter1' => $designer->name,
    'parameter2' => $design->title,
    'parameter3' => $reviewer->feedback,
    'images' => [
        'image1' => $design->preview_url,
        'image2' => $design->reference_url,
    ]
];
```

### 4. Meeting Notification with Audio
```php
$data = [
    'parameter1' => $attendee->name,
    'parameter2' => $meeting->title,
    'parameter3' => $meeting->scheduled_at,
    'audios' => [
        'audio1' => $meeting->previous_recording_url,
    ]
];
```

---

## Quick Reference Card

| Placeholder | Purpose | Data Structure |
|-------------|---------|----------------|
| `<parameter1>` | Text value | `'parameter1' => 'value'` |
| `<image1>` | Image URL | `'images' => ['image1' => 'url']` |
| `<audio1>` | Audio URL | `'audios' => ['audio1' => 'url']` |
| `<document1>` | Document URL | `'documents' => ['document1' => 'url']` |
| `<bubble>` | New line | No data needed |

---

**Last Updated**: 1 November 2025  
**Version**: 2.0  
**Compatible with**: NotificationService v2.0+

For more information, see:
- `NOTIFICATION_SERVICE_DOCUMENTATION.md` - Full service guide
- `NOTIFICATION_SERVICE_QUICK_REFERENCE.md` - Quick usage examples
- `NOTIFICATION_SERVICE_README.md` - Overview and architecture
