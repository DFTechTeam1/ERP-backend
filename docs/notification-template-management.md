# Notification Template Management — API Contract & Frontend Design

**Audience:** frontend engineer building the admin screen that manages notification templates.
**Stack (frontend):** Vue 3 + Vuetify.
**Backend source of truth:** `app/Services/NotificationService.php`, table `notification_settings`
(`Modules/Company/Models/NotificationSetting.php`).

> **Status:** the backend API in §2 is **implemented** (Company module:
> `NotificationTemplateController` + `NotificationTemplateService` + `NotificationSettingRepository`,
> routes under `/api/v1/notification-templates`, covered by
> `tests/Feature/Company/NotificationTemplateApiTest.php`). The frontend (§3) is still to build.
> JSON is **snake_case** and success responses use **HTTP 201** (house convention — see §2.1).

---

## 1. What a "notification template" is

A template is a single row in `notification_settings`. At send time,
`NotificationService::send($recipient, $action, $data, $channels)` looks the row up by
**`action`**, then substitutes placeholders from `$data` into the stored `template` (plain text)
and `template_html` (HTML) before delivering over the chosen channels.

### 1.1 Field reference

JSON keys are **snake_case** and match the DB columns 1:1 (house convention — no camelCasing).

| Field (API key = DB column) | Type | Notes |
|---|---|---|
| `id` | int | Primary key |
| `action` | string | **Integration key.** The exact string callers pass to `NotificationService::send()`, e.g. `user_has_been_assigned_to_task`. Must be unique, lowercase `^[a-z0-9_]+$`. **Immutable after create** (the update endpoint ignores it — see §5). |
| `template` | text | Plain-text body, placeholders as `<...>` |
| `template_html` | text \| null | HTML body, placeholders as **escaped** `&lt;...&gt;` |
| `notification_channel` | string[] | Allowed channels: subset of `email`, `slack`, `telegram`, `database` (returned as a decoded array) |
| `created_at` | datetime | |
| `updated_at` | datetime \| null | |

> **Removed (2026-11-07):** `trigger_event`, `target_audience` and `frequency` were dropped — they
> were stored-but-unread scaffolding for an event-driven auto-send feature that was never built.
> Only `notification_channel` was kept. If that feature is designed later, re-introduce them then.

### 1.2 Placeholder grammar (the heart of the editor)

`processTemplate()` recognises these tokens:

| Purpose | In `template` (plain) | In `template_html` (HTML) | Filled from `data` |
|---|---|---|---|
| Text value | `<parameter1>`, `<parameter2>`, … | `&lt;parameter1&gt;`, … | `data.parameter1`, … |
| Image | `<image1>`, … | `&lt;image1&gt;` → `<img>` | `data.images.image1` (URL) |
| Audio | `<audio1>`, … | `&lt;audio1&gt;` → `<audio>` | `data.audios.audio1` (URL) |
| Document | `<document1>`, … | `&lt;document1&gt;` → `<a download>` | `data.documents.document1` (URL) |
| New line | `<bubble>` | `&lt;bubble&gt;` → `<br>` | — (literal line break) |

**Critical gotchas for the editor:**

1. **Plain vs HTML bracket encoding.** In `template` the token is literally `<parameter1>`.
   In `template_html` it **must be HTML-escaped** as `&lt;parameter1&gt;`, because the backend
   replaces the escaped form. A WYSIWYG that emits a raw `<parameter1>` tag will **silently fail**
   to substitute. Design the HTML editor so inserted tokens are stored escaped.
2. **Plain output is `strip_tags()`-ed.** Any inline markup in `template` (e.g. `<b>`) is removed
   for the plain/text channels (Telegram/DB message), so don't rely on it for emphasis there.
3. **Params are 1-indexed and sequential by convention** (`parameter1`, `parameter2`, …). The count
   differs per template. The editor should help authors add the next index and keep plain/HTML in sync.

### 1.3 Real example (seeded `action: user_has_been_assigned_to_task`)

```
template:
  Hi <parameter1>, kamu ditugaskan ke task <b><parameter2></b> di project <b><parameter3></b> oleh <parameter4>.<bubble>Silahkan cek detail task dan mulai kerjakan 💪

template_html:
  <p>Hi &lt;parameter1&gt;, kamu ditugaskan ke task <strong>&lt;parameter2&gt;</strong> di project <strong>&lt;parameter3&gt;</strong> oleh &lt;parameter4&gt;.</p><p>Silahkan cek detail task dan mulai kerjakan 💪</p>
```

Sent with:
```json
{ "parameter1": "Zola", "parameter2": "Design Homepage", "parameter3": "Website Redesign", "parameter4": "Lead Designer" }
```

---

## 2. API Contract

### 2.1 Conventions

- **Base path:** `/api/v1/notification-templates` (behind `auth.session`).
- **Success body:** `{ "message": string, "data": object }`, HTTP status **`201`** (house
  convention — `generalResponse` defaults to 201, including for GETs). There is **no `error` flag
  in the body** (the `apiResponse` helper omits it) — decide success/failure from the HTTP status.
- **Not found:** HTTP `404`, body `{ "message": "Notification template not found.", "data": [] }`.
- **Validation error (422):** `{ "message": string, "errors": { field: string[] } }` (Laravel default).
- **List body:** `data: { "paginated": [...rows], "totalData": int }`.
- **List query params:** `page` (1-based), `itemsPerPage` (default 15), `search` (string, matches
  `action`/`template`), `sort` (array of `{ field, order }`; sortable fields: `id`, `action`,
  `created_at`, `updated_at`; default `updated_at desc`), optional filter `channel`
  (one of the four channels).
- JSON keys are **snake_case** (match the DB columns per §1.1).

### 2.2 Endpoints

#### List — `GET /api/v1/notification-templates`
Query example: `?page=1&itemsPerPage=15&search=task&channel=email&sort[0][field]=updated_at&sort[0][order]=desc`
```json
// 201
{
  "message": "Success",
  "data": {
    "paginated": [
      {
        "id": 4,
        "action": "user_has_been_assigned_to_task",
        "template": "Hi <parameter1>, kamu ditugaskan ke task <b><parameter2></b> …",
        "template_html": "<p>Hi &lt;parameter1&gt; …</p>",
        "notification_channel": ["database", "email"],
        "created_at": "2025-10-30 15:32:31",
        "updated_at": null
      }
    ],
    "totalData": 10
  }
}
```

#### Detail — `GET /api/v1/notification-templates/{id}`
`201 { "message": "Success", "data": { ...template } }` · `404` if not found.

#### Create — `POST /api/v1/notification-templates`
```json
// request
{
  "action": "task_has_been_hold_by_user",
  "template": "Hi <parameter1>, tugas <b><parameter2></b> di-hold oleh <parameter3>.<bubble>Alasan: <b><parameter4></b>",
  "template_html": "<p>Hi &lt;parameter1&gt; …Alasan: <strong>&lt;parameter4&gt;</strong></p>",
  "notification_channel": ["database", "telegram"]
}
// 201
{ "message": "Notification template created.", "data": { "id": 11, "action": "task_has_been_hold_by_user", "...": "..." } }
```

#### Update — `PUT /api/v1/notification-templates/{id}`
Same body as create **minus `action`** — any `action` in the body is ignored (immutable; see §5).
→ `201 { "message": "Notification template updated.", "data": { ...template } }` · `404` if not found.

#### Delete — `DELETE /api/v1/notification-templates/{id}`
→ `201 { "message": "Notification template deleted.", "data": [] }` · `404` if not found.

#### Metadata / reference — `GET /api/v1/notification-templates/metadata`
Drives the editor's pickers and placeholder palette (no hard-coding on the frontend).
```json
{
  "message": "Success",
  "data": {
    "channels": ["email", "slack", "telegram", "database"],
    "placeholders": [
      { "token": "<parameterN>", "html_token": "&lt;parameterN&gt;", "type": "text",     "repeatable": true },
      { "token": "<imageN>",     "html_token": "&lt;imageN&gt;",     "type": "image",    "repeatable": true },
      { "token": "<audioN>",     "html_token": "&lt;audioN&gt;",     "type": "audio",    "repeatable": true },
      { "token": "<documentN>",  "html_token": "&lt;documentN&gt;",  "type": "document", "repeatable": true },
      { "token": "<bubble>",     "html_token": "&lt;bubble&gt;",     "type": "newline",  "repeatable": true }
    ]
  }
}
```
> `channels` is the fixed list of delivery channels. `placeholders` is the authoritative token
> palette for the editor.

#### Live preview — `POST /api/v1/notification-templates/preview`
Renders a draft without saving — powers the live preview pane.
```json
// request
{
  "template": "Hi <parameter1> …",
  "template_html": "<p>Hi &lt;parameter1&gt; …</p>",
  "data": { "parameter1": "Zola", "parameter2": "Design Homepage", "images": { "image1": "https://…/x.png" } }
}
// 201
{ "message": "Success", "data": { "rendered": "Hi Zola …", "rendered_html": "<p>Hi Zola …</p>" } }
```

### 2.3 Validation rules (summary)

| Field | Rules |
|---|---|
| `action` | required, string, `^[a-z0-9_]+$`, unique on `notification_settings.action`. **Create only** — the update request does not accept it. |
| `template` | required, string |
| `template_html` | nullable, string |
| `notification_channel` | required, array, min 1; each `in:email,slack,telegram,database` |

> Uniqueness is enforced at the validation layer (`Rule::unique`). A DB unique index on
> `action` is still recommended (see §5) but is not yet added.

---

## 3. Frontend Design

Two screens: a **List** (browse/search/manage) and a **Form** (create/edit with live preview).
Delete is a confirm dialog. The Form is best as a **full page** (the live-preview pane needs width);
a wide `v-navigation-drawer` / full-screen `v-dialog` is an acceptable alternative.

### 3.1 Navigation / IA

```
Settings ▸ Notifications ▸ Templates
  └── /settings/notifications/templates            (List)
  └── /settings/notifications/templates/create     (Form - create)
  └── /settings/notifications/templates/:id/edit   (Form - edit)
```

### 3.2 List screen

```
┌───────────────────────────────────────────────────────────────────────────────┐
│ Notification Templates                                   [ + New Template ]     │
│                                                                                 │
│ [ 🔍 Search action or text… ]  [ Channel ▾ ]                      (clear)      │
│                                                                                 │
│ ┌─────────────────────────────────────────────────────────────────────────┐   │
│ │ Action                      Channels            Updated   ⋮              │   │
│ ├─────────────────────────────────────────────────────────────────────────┤   │
│ │ user_has_been_assigned_…    [db][email]         2d ago    ⋮              │   │
│ │ ▸ "Hi <parameter1>, kamu ditugaskan ke task <parameter2>…"                │   │
│ │ task_has_been_hold_by_user  [db][telegram]      5d ago    ⋮              │   │
│ │ ▸ "Hi <parameter1>, tugas <parameter2> di-hold oleh…"                     │   │
│ └─────────────────────────────────────────────────────────────────────────┘   │
│                               rows: [15 ▾]        ‹ 1 2 3 ›     10 of 10         │
└───────────────────────────────────────────────────────────────────────────────┘
```

**Columns**
- **Action** — the key, rendered as a monospace / `v-chip` code chip (it's an identifier, not prose).
  Secondary line: a truncated, placeholder-stripped snippet of `template` for human context.
- **Channels** — one `v-chip` per entry in `notification_channel` (color per channel:
  email=blue, slack=purple, telegram=sky, database=gray).
- **Updated** — relative time; tooltip with absolute `updated_at`.
- **Actions (⋮)** — Edit, Preview, Delete.

**Toolbar**
- Debounced search box (~300ms) → `search` param (matches `action` and `template`).
- `Channel` multi-select filter → `channel` param.
- Column header click → `sort` (`{field, order}`); default `updated_at desc`.

**Behaviours / states**
- Server-side pagination with `v-data-table-server`: bind `:items-length="totalData"` and handle
  `@update:options` (`{ page, itemsPerPage, sortBy }`) → map to the `page`, `itemsPerPage`, `sort`
  query params and refetch. Combine with the debounced `search`/filter values.
- Loading: `:loading` on the table (skeleton/linear bar). Empty (no data): `#no-data` slot with an
  illustration + "Create your first template".
- Empty (filtered): "No templates match your filters" + clear-filters action.
- Row click (or Preview action) opens a **read-only preview dialog** (rendered plain + HTML tabs).

**Vuetify mapping:** `v-data-table-server` (server-side rows/pagination/sort; use an
`item.action` slot for the code chip + snippet secondary line), `v-text-field` (search),
`v-select`/`v-autocomplete` with `multiple` (channel filter), `v-chip` (channels), `v-menu` +
`v-list` (row ⋮), built-in table pagination (or `v-pagination`), `v-btn`, `v-dialog` (preview and
delete confirm).

### 3.3 Form screen (create / edit)

Two-column layout: **editor on the left, live preview on the right**. On mobile, stack
(editor first, preview collapsible).

```
┌───────────────────────────────── Create / Edit Template ──────────────────────────────────┐
│ [ ← Back ]                                                    [ Cancel ]  [ Save template ] │
├──────────────────────────────────────────┬─────────────────────────────────────────────────┤
│ EDITOR                                     │ LIVE PREVIEW                                     │
│                                            │                                                  │
│ Action *   [ user_has_been_assigned… ] 🔒  │  Sample data                                     │
│   (lowercase, snake_case, unique)          │  parameter1 [ Zola            ]                  │
│ Channels *   [✓ database] [✓ email]        │  parameter2 [ Design Homepage ]                  │
│              [ ] slack  [ ] telegram       │  parameter3 [ Website Redesign]                  │
│                                            │  parameter4 [ Lead Designer   ]                  │
│ Placeholders: [param][image][audio]        │  image1     [ https://…/x.png ]                  │
│               [document][bubble]           │  ───────────────────────────────                 │
│                                            │  [ Text ]  [ HTML ]        ⟳ (debounced)         │
│ Template (plain) *                         │  ┌───────────────────────────────────────────┐  │
│ ┌────────────────────────────────────────┐│  │ Hi Zola, kamu ditugaskan ke task Design   │  │
│ │ Hi <parameter1>, kamu ditugaskan ke    ││  │ Homepage …                                │  │
│ │ task <b><parameter2></b> …<bubble>…    ││  └───────────────────────────────────────────┘  │
│ └────────────────────────────────────────┘│                                                  │
│ Template (HTML)                            │                                                  │
│ ┌────────────────────────────────────────┐│                                                  │
│ │ <p>Hi &lt;parameter1&gt; …</p>         ││                                                  │
│ └────────────────────────────────────────┘│                                                  │
└──────────────────────────────────────────┴─────────────────────────────────────────────────┘
```

**Fields** (Vuetify components in parentheses)
- **Action** (`v-text-field`) — slugify-on-type (lowercase, spaces→`_`). On **edit**, set
  `disabled`/`readonly` with a lock icon + hint "Used by backend senders; cannot be changed"
  (see §5). Map the unique-constraint error from the 422 payload to `:error-messages`.
- **Channels** (`v-select multiple chips` or a `v-checkbox` group) — from `metadata.channels`;
  at least one required.
- **Placeholder palette** — a row of clickable `v-chip` / `v-btn` tokens. Clicking inserts the token
  **at the caret** of the currently focused template field (track the field's selection range and
  splice the token in). "Parameter" inserts the next index (`parameter1`, then `parameter2`, …).
  For the HTML field, insert the **escaped** form (`&lt;parameter1&gt;`) automatically.
- **Template (plain)** (`v-textarea`, monospace, auto-grow) — required.
- **Template (HTML)** — either (a) an **HTML-source `v-textarea`** (simplest, matches backend
  escaping exactly), or (b) a WYSIWYG that **must serialise tokens as escaped** `&lt;…&gt;`.
  Recommend (a) for v1 to avoid the escaping trap; add a small "Insert token" helper.

> Wrap the form in `v-form` and use a `v-btn`'s `:loading`/`:disabled` for the save state.
> Vuetify rules run client-side; server 422 messages still map onto each field's `:error-messages`.

**Live preview panel**
- A **Sample data** editor: `v-text-field` rows auto-derived from the placeholders detected in the
  template (prefill `parameter1…N`, plus `image1`, etc.). Author fills sample values.
- Calls `POST /notification-templates/preview` (debounced ~500ms) with the current draft + sample
  data; renders **Text** and **HTML** tabs (`v-tabs` + `v-window`) from `rendered` / `rendered_html`.
- Render the HTML tab in a sandboxed container (`v-html` inside an isolated wrapper, or an iframe)
  since it contains arbitrary HTML.

**Validation UX**
- Inline field errors mapped from the 422 `errors` object (keys are snake_case field names, e.g.
  `template`, `notification_channel`, `notification_channel.0`).
- **Placeholder-consistency warning** (client-side, non-blocking): diff the token set found in
  `template` vs `template_html`; if they differ, show a warning ("`<parameter4>` is in the plain
  template but missing from the HTML version"). This catches the most common authoring mistake.
- Disable **Save** while required fields are empty or a preview/validation request is in flight.

**Save flow**
- Create → `POST`; on `201`, toast "Template created" and route to the list (or to edit).
- Edit → `PUT`; on `200`, toast "Template updated".
- On `422`, surface field errors; on other non-2xx, toast the `message`.

### 3.4 Delete

`v-dialog` confirm: "Delete template `{action}`? Senders referencing this action will stop
delivering." Require explicit confirm. On success, toast + refresh list. (See §5 — backend may
soft-guard this.)

### 3.5 Preview (from the list)

Reuse the preview call with the row's stored `template`/`template_html` and either empty or
auto-generated sample data, shown in a modal with Text/HTML tabs — a quick "what does this look
like?" without entering edit mode.

---

## 4. Suggested component/state checklist (frontend)

- [ ] `useNotificationTemplates()` composable: `list(params)`, `get(id)`, `create(body)`,
      `update(id, body)`, `remove(id)`, `metadata()`, `preview(body)`.
- [ ] List page with server-side pagination, search, filters, sort.
- [ ] Row actions: edit / preview / delete.
- [ ] Form page (create + edit share one component) with placeholder palette + caret insertion.
- [ ] Live preview pane with sample-data editor + debounced `preview` call + Text/HTML tabs.
- [ ] Placeholder-consistency warning between plain and HTML.
- [ ] 422 error mapping to fields; toast for other errors.
- [ ] `action` locked on edit.

---

## 5. Open questions / follow-ups

1. **`action` immutability — DONE; DB unique index — open.** The update endpoint already ignores
   `action` (immutable) and create validates uniqueness via `Rule::unique`. A DB unique index on
   `notification_settings.action` is still recommended for hard integrity (the create migration has
   none today); not added yet because it is a schema change worth confirming first.
2. **`target_audience` / `frequency` / `trigger_event` — REMOVED (2026-11-07).** These columns were
   stored but read by nothing, so they were dropped (migration + model + API + Bruno + tests). If an
   event-driven auto-send / audience / cadence feature is designed later, re-introduce them then and
   wire them to real behaviour. `notification_channel` was kept.
3. **Placeholder-consistency validation.** Not enforced server-side yet. Should the backend reject
   (or just warn) when `template` and `template_html` reference different placeholder sets? (The
   frontend already shows a non-blocking warning.)
4. **Delete safety.** Currently a hard delete. Consider soft-delete or a guard when a template is
   still referenced by active senders.
5. **Auth / permissions.** The routes use `auth.session` only (same as the Branch CRUD) — no
   fine-grained permission yet. Decide which role(s) may manage templates and gate the group.
```
