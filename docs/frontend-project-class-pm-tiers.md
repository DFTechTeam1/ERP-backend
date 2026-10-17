# Frontend task — PM‑count reward tiers on the Event Class (Project Class) master

You are working on the **Dfactory ERP frontend** (Vue.js / Nuxt.js, Nuxt UI v3, Tailwind CSS).
This task adds **per‑PM‑count reward tiers** to the existing **Event Class** master
(Master → Event Class). Read the whole doc before coding; follow the existing module's
patterns (components, currency input, form validation, modal styling).

## 1. Background

Each Event Class defines the reward payout for a completed event. Today the create/edit
modal has:

- **Basic Information**: Event Class Name, Color
- **Reward Configuration**: three flat currency fields — Production Reward, PM Reward, VJ Reward

List columns: Name, Production Reward, PM Reward, VJ Reward, Status, Action.

## 2. Why this change

The company tariff ("Tarif Plan") pays a **different PM pot and Production pot depending on
how many PMs collaborate** on the event — but only for some classes (currently class **S**).
VJ reward is always a flat per‑VJ amount and does **not** change with PM count.

Example — class **S**:

| PMs | PM Reward | Production Reward |
|----:|----------:|------------------:|
| 1   | 2,500,000 | 3,500,000         |
| 2   | 2,500,000 | 4,000,000         |
| 3   | 3,000,000 | 4,500,000         |

VJ (flat) = 350,000.

All other classes (A, B, C, D, B+, A+) have a single flat reward — **no tiers**.

So the model is: an event class has **base/flat rewards** plus an optional list of
**PM‑count tiers**. A class with no tiers behaves exactly as it does today.

## 3. Data model (backend context — you don't build this)

- `project_classes` keeps the flat columns: `reward` (production, fallback), `pm_reward`
  (PM, fallback), `vj_reward` (flat, always used).
- A new child table holds tiers: each row is `{ pm_count, pm_reward, production_reward }`
  scoped to a class. Only tiered classes (S) have rows.
- **Resolution at reward time** (backend): pick the tier with the **highest `pm_count ≤` the
  event's PM count**; if none matches, fall back to the flat base values. This is why a class
  with no tiers keeps working unchanged. You don't implement this — it's only so the UI copy
  and validation make sense.

## 4. API contract (build against this — agreed with the backend session)

The **existing** Event Class detail / create / update endpoints gain a `pmTiers` field.
Field casing is camelCase, matching the rest of the API. Keep the base fields exactly as the
current endpoints already return them; just add `pmTiers`.

**Detail (GET) — response `data`:**

```json
{
  "uid": "…",
  "name": "S (Spesial)",
  "color": "#FFB74D",
  "reward": 3500000,
  "pmReward": 2500000,
  "vjReward": 350000,
  "isActive": true,
  "pmTiers": [
    { "pmCount": 1, "pmReward": 2500000, "productionReward": 3500000 },
    { "pmCount": 2, "pmReward": 2500000, "productionReward": 4000000 },
    { "pmCount": 3, "pmReward": 3000000, "productionReward": 4500000 }
  ]
}
```

**Create (POST) / Update (PUT) — request body** includes the base fields plus `pmTiers`
(an array, may be empty). **Full‑replace semantics**: send the complete desired tier list;
the backend replaces the class's existing tiers with exactly what you send. Omitting the key
or sending `[]` clears all tiers.

```json
{
  "name": "S (Spesial)",
  "color": "#FFB74D",
  "reward": 3500000,
  "pmReward": 2500000,
  "vjReward": 350000,
  "pmTiers": [
    { "pmCount": 1, "pmReward": 2500000, "productionReward": 3500000 },
    { "pmCount": 2, "pmReward": 2500000, "productionReward": 4000000 },
    { "pmCount": 3, "pmReward": 3000000, "productionReward": 4500000 }
  ]
}
```

Amounts are integers in the UI (no decimals); the backend stores `decimal(24,2)`.

## 5. UI changes

### 5.1 Create / Edit modal

Keep **Basic Information** (name, color) and **Reward Configuration** (Production, PM, VJ) as
they are — these are the **base / fallback** values. Refine the helper text:

- Production Reward → "Base production pot. Used when no PM tier matches."
- PM Reward → "Base PM pot. Used when no PM tier matches."
- VJ Reward → "Fixed reward per VJ. Always flat — not affected by tiers."

Add a new, **collapsible, optional** section titled **"PM Reward Tiers (optional)"**:

- Intro line: "Use tiers when the PM and production reward change with the number of PMs on
  the event (e.g. Class S). Leave empty for a single flat reward."
- A **repeatable list of rows**; each row has three inputs + a remove (trash) button:
  - **PM Count** — integer, min 1
  - **PM Reward** — IDR currency
  - **Production Reward** — IDR currency
- An **"Add tier"** button under the list.
- VJ is **not** part of tiers (do not add a VJ field here).

**Validation:**

- `pmCount` required, integer ≥ 1, **unique** within the list (block duplicates with an inline error).
- `pmReward` and `productionReward` required for any existing row, ≥ 0.
- Sort rows ascending by `pmCount` before submit (or show a hint if out of order).
- Optional cap of 5 tiers.
- Empty tier list is valid (flat class). Disable Save while any row is invalid.

**Currency inputs:** IDR, "Rp" prefix, thousand separators, integer only. Reuse the module's
existing currency input component / formatter.

### 5.2 List view

- Keep the existing columns (Name, Production/PM/VJ Reward showing the **base** values, Status,
  Action) unchanged.
- Optional nicety: show a small chip next to the Name when a class has tiers, e.g. `3 PM tiers`,
  so users can tell tiered classes apart at a glance. Skip if it complicates the row.

### 5.3 Preview row (bottom of the modal)

- Keep the current base preview. If tiers exist, optionally show a compact breakdown
  (PMs → PM / Production) below it or on hover. Optional.

## 6. States & polish

- Loading and error states on load/save.
- Inline field-level validation errors; Save disabled while invalid.
- Reuse Nuxt UI v3 form components and match the current modal's spacing/typography.
- Preserve tier order and values on edit → save → reload (round‑trip).

## 7. Acceptance criteria

- Creating/editing a class with tiers persists them and they reload correctly (round‑trip).
- A class with **no** tiers looks and behaves exactly as before.
- Duplicate `pmCount` and `pmCount < 1` are blocked with clear errors.
- VJ reward is unaffected by tiers.
- Empty `pmTiers` (or removing all rows) clears tiers via the full‑replace payload.

## 8. Out of scope

- The reward calculation itself (backend).
- How an event class is chosen on a project/deal (unchanged).
- Any change to VJ reward semantics.

## 9. Test data (class S, from the tariff sheet)

Name `S (Spesial)`, color `#FFB74D`, base `reward` 3,500,000, `pmReward` 2,500,000,
`vjReward` 350,000, and the three tiers in the JSON above. Use this to verify the round‑trip
end to end.
