# Frontend changes — PM/VJ reward + Lead PM

Backend branch: `fix/de-1`. This document describes the API changes the frontend must adopt for
the new reward schema (production + PM + VJ) and the Lead-PM feature. All amounts are IDR
(whole rupiah), stored as decimals.

> Context: rewards are calculated **automatically by the backend** when a project is completed.
> The frontend does not compute any amount — it only (a) configures the per-class pots, (b) lets a
> project designate which PM is the Lead, and (c) displays main/support PM info. The amounts are 0
> until each project class has its pots configured.

---

## 1. New endpoint — Set Lead PM

`POST /api/production/project/{projectUid}/setLeadPic`

Marks one of the project's PMs (PICs) as the **Lead**. Exactly one Lead per project — any previous
Lead is demoted automatically. The Lead takes the largest share of the PM reward pot
(1 PM → 100%, 2 PM → 70% Lead / 30% Support, 3 PM → 50% / 25% / 25%).

**Request body**
```json
{ "employee_uid": "<employee-uid>" }
```

**Success — 201**
```json
{ "error": false, "message": "Lead project manager was set successfully", "data": [], "code": 201 }
```

**Errors**
- `400` — the employee is not a PIC of this project:
  `{ "error": true, "message": "This employee is not a person in charge of this project", "code": 400 }`
- `422` — `employee_uid` missing.

**Frontend work**
- On the project detail screen, add a control (e.g. a "Set as Lead" action / radio on each PM) that
  calls this endpoint with the chosen PM's employee uid.
- After a successful call, re-fetch the project detail (the backend clears its detail cache, so the
  refreshed `main_pm` / `support_pms` come back on the next GET).

---

## 2. Changed — Assign PIC (optional Lead nomination)

`POST /api/production/project/{projectUid}/assignPic`

**Request body** (new optional `lead`)
```json
{
  "pics": ["<employee-uid>", "<employee-uid>"],
  "lead": "<employee-uid>"        // optional: which PM is the Lead
}
```
- `lead` is optional. If omitted, no PM is flagged and the Lead falls back to the
  earliest-assigned PIC until it is set explicitly (via this field or `setLeadPic`).

**Frontend work**
- Optional: when assigning PMs, let the user pick which one is the Lead and send it as `lead`.
- If you prefer a simpler flow, skip `lead` here and set the Lead later with `setLeadPic`.

---

## 3. Changed — Project detail response (main/support PM)

`GET /api/production/project/{projectUid}` → payload at `data.data`.

**New fields**
```jsonc
{
  // ... existing fields ...

  // each PIC now carries is_lead
  "person_in_charges": [
    { "id": 12, "pic_id": 34, "project_id": 5, "is_lead": true, "employee": { "...": "..." } }
  ],

  // labeled list of PMs
  "project_managers": [
    { "uid": "emp-uid-1", "employee_id": "DF-001", "name": "Yanuar", "is_lead": true,  "role": "main" },
    { "uid": "emp-uid-2", "employee_id": "DF-002", "name": "Nando",  "is_lead": false, "role": "support" }
  ],

  // convenience split
  "main_pm": { "uid": "emp-uid-1", "employee_id": "DF-001", "name": "Yanuar", "is_lead": true, "role": "main" },
  "support_pms": [
    { "uid": "emp-uid-2", "employee_id": "DF-002", "name": "Nando", "is_lead": false, "role": "support" }
  ]
}
```
- `role` is `"main"` (the Lead) or `"support"`.
- `main_pm` is `null` when the project has no PMs.
- If no PM is flagged `is_lead` (legacy projects), the backend still marks the earliest-assigned PM
  as `main`, so `main_pm` is always populated when at least one PM exists.

**Frontend work**
- Show which PM is the **main** (e.g. a "Lead" badge) vs **support** using `project_managers` (or
  `main_pm` / `support_pms`).
- Pair this with the "Set as Lead" control from section 1.

---

## 4. Changed — Project Class create / update / list (PM & VJ pots)

Project classes gain two new configurable amounts alongside the existing `reward` (the production
pot):
- `pm_reward` — total PM pot for the event (split across the PMs by the Lead/Support rule).
- `vj_reward` — fixed amount **per VJ** (every VJ on the project earns this full amount).

### Create — `POST /api/projectClass`
### Update — `PUT /api/projectClass/{id}`

**Request body** (new optional fields)
```jsonc
{
  "name": "S (Spesial)",
  "color": "#FFB74D",
  "reward": 4000000,       // existing: production pot
  "pm_reward": 2500000,    // NEW: total PM pot
  "vj_reward": 350000       // NEW: per-VJ amount
}
```
- `pm_reward` / `vj_reward` are optional (`nullable|numeric|min:0`); when omitted they default to `0`.
- `reward` remains required (unchanged).

**Response** now returns the saved class so the form can reflect it immediately:
```json
{
  "error": false,
  "message": "...",
  "data": { "uid": 2, "name": "S (Spesial)", "color": "#FFB74D", "reward": 4000000, "pm_reward": 2500000, "vj_reward": 350000, "status": 1 },
  "code": 201
}
```

### List — `GET /api/projectClass`
Each row in `data.paginated` now includes `pm_reward` and `vj_reward` (in addition to the existing
`reward`).

**Frontend work**
- Add **PM Reward** and **VJ Reward** number inputs to the project-class create/edit form
  (currency IDR, allow large values / millions).
- Show the two new columns/values in the project-class list and edit form (prefill from the list
  row or the create/update response).

### Reference — tariff per class (from finance's baseline sheet)
For seeding/verifying the values (finance owns the source of truth):

| Class | reward (production) | pm_reward | vj_reward |
|-------|--------------------:|----------:|----------:|
| S     | 3,500,000 – 4,500,000 | 2,500,000 – 3,000,000 | 350,000 |
| A     | 2,000,000 | 1,250,000 | 200,000 |
| B     | 1,000,000 | 1,000,000 | 125,000 |
| C     |   500,000 |   250,000 | 100,000 |

(S varies by PM count in the sheet, but each class stores a single configured value.)

---

## 5. Notes / optional follow-ups

- **When rewards are recorded:** automatically at project completion, once **all** PMs have
  completed the project (the last completion triggers it). Nothing to build on the frontend for the
  calculation itself.
- **Cost estimation** (`GET /api/production/cost-estimation/{projectUid}`): the reward list now
  includes PM and VJ reward rows mixed with production ones. The list item shape is unchanged
  (`id, name, avatar, total_point, total_reward`). If the UI needs to visually separate
  production / PM / VJ, tell the backend team — a `role` field can be added to that response
  (not included yet).
- **No breaking changes:** all new request fields are optional and all new response fields are
  additive; existing screens keep working without changes.
