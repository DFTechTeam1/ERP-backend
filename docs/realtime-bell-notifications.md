# Realtime Bell Notifications

How to send a notification that appears in the frontend bell — stored in the
database **and** pushed live over Pusher — using `RealtimeNotificationService`.

This flow only ever touches the Laravel **database** notification channel. It
does not send email, Slack, WhatsApp, Line, or Telegram — those are unchanged.

---

## TL;DR

```php
use App\Services\RealtimeNotificationService;

app(RealtimeNotificationService::class)->send(
    recipients: $user,                                  // User|Employee, or a collection
    topic: RealtimeNotificationService::TOPIC_DIVISION, // or TOPIC_GENERAL
    payload: [
        'title'   => 'New task assigned',
        'message' => 'You have a new task in Compositing',
        'icon'    => '📋',
        'url'     => '/admin/production/board',
        'action'  => 'user_has_been_assigned_to_task',
        'data'    => ['task_id' => 123],
    ],
    divisionId: $divisionId,                            // required when topic = division
);
```

One call does two things atomically per recipient:

1. **Persists** a Laravel database notification (survives reload; feeds the badge count and history).
2. **Pushes** the identical payload over Pusher so the bell updates instantly.

The stored row and the pushed item share the same `id`, so mark-as-read works on both.

---

## Topics — the two bell tabs

The frontend bell has exactly two tabs, driven by the `topic` field:

| Topic       | Constant                                | Tab shown in the bell                        |
|-------------|-----------------------------------------|----------------------------------------------|
| `general`   | `RealtimeNotificationService::TOPIC_GENERAL`  | **General**                            |
| `division`  | `RealtimeNotificationService::TOPIC_DIVISION` | **Division** (labelled with the user's division name) |

- `TOPIC_GENERAL` — company-wide / cross-division notifications.
- `TOPIC_DIVISION` — division-scoped. You must pass `divisionId`. It is stored on
  the notification so it lands in the recipient's Division tab.

> Recipients are always explicit (see below). A user only ever receives what you
> address to them, so the Division tab already shows only their own division's items.

---

## Recipients

Pass **who** should receive the notification. Accepted:

- A single `App\Models\User`
- A single `Modules\Hrd\Models\Employee`
- Any collection/array of the above (mixed is fine)

```php
// one user
$service->send($user, RealtimeNotificationService::TOPIC_GENERAL, $payload);

// an employee
$service->send($employee, RealtimeNotificationService::TOPIC_GENERAL, $payload);

// many recipients at once
$service->send($users, RealtimeNotificationService::TOPIC_DIVISION, $payload, divisionId: 7);
```

The notification is stored against each recipient (User or Employee — both are
`Notifiable` and write to the same `notifications` table). The live push is sent
to the owning user's private channel:

- `User`     → pushes to that user's channel.
- `Employee` → pushes to `employee.user_id`'s channel. If the employee has no
  linked user, the row is still stored but no live push is sent.

### Sending to a whole division

There is no "resolve division members" helper — you pass the members explicitly.
Resolve them however your call site already does, e.g.:

```php
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;

$employees = Employee::whereIn(
    'position_id',
    PositionBackup::where('division_id', $divisionId)->pluck('id'),
)->get();

$service->send($employees, RealtimeNotificationService::TOPIC_DIVISION, $payload, divisionId: $divisionId);
```

---

## Payload

| Key       | Type     | Required | Notes                                                        |
|-----------|----------|----------|--------------------------------------------------------------|
| `title`   | string   | no*      | Bell item title. Defaults to `''`.                           |
| `message` | string   | no*      | Body. Rendered with `v-html` in the frontend.                |
| `icon`    | string   | no       | Emoji shown next to the item. Defaults to `🔔`.              |
| `url`     | string   | no       | Click target. Relative (`/admin/...`) → SPA route; absolute (`https://...`) → new tab. |
| `action`  | string   | no       | Machine-readable action key for your own logic/analytics.    |
| `data`    | array    | no       | Any extra context you want to carry. Defaults to `[]`.       |

\* `title` + `message` are what the user reads — always provide them in practice.

The stored/pushed record looks like:

```json
{
  "id": "9b1f...uuid",
  "action": "user_has_been_assigned_to_task",
  "title": "New task assigned",
  "message": "You have a new task in Compositing",
  "icon": "📋",
  "url": "/admin/production/board",
  "topic": "division",
  "division_id": 7,
  "data": { "task_id": 123 },
  "read": false,
  "created_at": "17 July 2026 09:30"
}
```

---

## Validation / errors

`send()` throws `InvalidArgumentException` when:

- `topic` is anything other than `general` or `division`.
- `topic` is `division` but `divisionId` is `null`.

---

## Reading & marking as read (frontend endpoints)

These already exist and are consumed by the bell — you normally don't call them:

| Method & path                         | Purpose                                                        |
|---------------------------------------|---------------------------------------------------------------|
| `GET /api/user/notifications`         | Returns unread items bucketed as `{ general, division, divisionName }` (AES-encrypted envelope). |
| `GET /api/notification/markAsRead/{id}` | Marks a single notification read.                           |
| `GET /api/notification/readAll`       | Marks all read (both User and Employee notifiables).          |

Bucketing is by the stored `topic`. Legacy notifications without a `topic` are
treated as `general` (so nothing is silently dropped).

---

## Delivery transport (Pusher, private channel)

- **Channel:** `private-notifications.{userId}` — a private, authenticated channel.
  A user can only subscribe to their own.
- **Event:** `new-db-notification`.
- **Auth endpoint:** `POST /api/notifications/channel-auth`, guarded by `auth.session`
  (the RS256 Bearer guard). It signs the subscription only if the channel belongs
  to the authenticated user; otherwise `403`.
- Delivery is a **direct** Pusher trigger (`App\Services\PusherNotification`), not
  Laravel event broadcasting — so `BROADCAST_CONNECTION` does **not** need to be
  `pusher`.

This is separate from the legacy public `my-channel-{id}` channel, which is left
untouched.

### Required environment

```dotenv
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
```

Frontend needs `VITE_PUSHER_KEY` and `VITE_PUSHER_APP_CLUSTER` set to the same
app. Without a real key/secret the bell still loads from the database, but no
live push arrives.

---

## What this flow does NOT do

- It does not send email/Slack/WhatsApp/Line/Telegram — database + bell only.
- It does not resolve division membership for you — pass explicit recipients.
- It does not rewrite existing notification classes — call `send()` where you want realtime.

---

## Reference

- Service: `app/Services/RealtimeNotificationService.php`
- Notification: `app/Notifications/RealtimeDatabaseNotification.php`
- Pusher trigger/auth: `app/Services/PusherNotification.php`
- Read/bucket: `app/Services/UserService.php` → `getApplicationNotification()`
- Auth route: `routes/api.php` → `POST /api/notifications/channel-auth`
- Tests: `tests/Feature/RealtimeNotificationServiceTest.php`
