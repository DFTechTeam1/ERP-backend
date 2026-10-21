# Chart of Accounts - API Contract

API contract for the **Chart of Accounts (Bagan Akun)** screen
(`/admin/accounting/chart-of-accounts`) and its edit rules. This is what the
Vue frontend (`src/pages/admin/accounting/generalLedger/chartOfAccounts/`,
`src/stores/accountingChartOfAccounts.js`) will call once the backend is ready -
today the store runs on in-memory dummy data and every action has a matching
`// TODO(accounting API)` marker.

Companion to [Account Edit Rules](./fiscal-period-actions-validation.md) and the
Chart of Accounts rules doc. Base currency **IDR**, standard **PSAK**.

---

## 1. Conventions

- **Base URL:** from env (`VITE_API_BASE` / `NUXT_PUBLIC_API_BASE`). All paths
  below are relative to it, e.g. `GET {base}/accounting/chart-of-accounts`.
- **Auth:** `Authorization: Bearer <access_token>` on every request (Axios
  interceptor). `401` → token refresh → retry → on failure logout.
- **Casing:** request bodies **snake_case** in; response bodies **camelCase**
  out (Spatie Laravel Data resources).
- **Identity:** every resource is addressed and returned by its `uid` (an opaque
  string). The backend keeps an internal numeric `id` but **never returns it**;
  all path params (`{uid}`) and foreign keys (`parentUid` / `parent_uid`) use
  `uid`. This holds for every entity in the accounting module.
- **Success envelope:** `{ "data": <payload>, "message": "<string>" }`.
- **Money:** `balance` is an **integer in IDR** (no decimals/minor units).
  Debit-normal balances are positive; contra accounts carry the sign of their
  presentation (e.g. Accumulated Depreciation is negative under Assets).
- **Multi-tenancy:** every query is scoped to the caller's tenant server-side.
- **Permissions:** enforced server-side; the matrix is in §7. The frontend also
  gates the UI, but the backend is authoritative.

### Error envelope

Validation (`422`):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["This account code already exists."]
  }
}
```

Business-rule rejection (`409 Conflict` for state rules, `403 Forbidden` for
permission/locked):

```json
{
  "message": "This account has posted transactions. Its code can no longer be changed.",
  "error_code": "ACCOUNT_HAS_TRANSACTIONS"
}
```

`error_code` values are catalogued in §6.

---

## 2. The Account resource (response shape, camelCase)

```jsonc
{
  "uid": "128",                      // public identifier (opaque string)
  "code": "1-1003",                  // display/reference code (unique per tenant)
  "name": "Bank Mandiri - 1400xxxx",
  "accountType": "asset",            // asset | liability | equity | revenue | expense
  "accountSubtype": "cash_bank",     // grouping within a type (nullable)
  "normalBalance": "debit",          // debit | credit - DERIVED, never client-set (§5)
  "parentUid": "120",                   // null = top-level group
  "level": 2,                        // depth, 0-based - server-computed
  "isPostable": true,                // true = leaf (can receive journal lines); false = header/group
  "isActive": true,
  "isLocked": true,                  // system account (default_accounts/tax_codes/bank_accounts/asset_categories)
  "hasTransactions": true,           // ≥1 journal_lines row references this account
  "isCashEquivalent": true,          // Cash Flow presentation (nullable)
  "cashFlowCategory": "operating",   // operating | investing | financing (nullable)
  "balance": 1210000000,             // current balance in IDR (leaf: own; header: sum of descendants)
  "children": []                     // present only in tree view (§3.1)
}
```

### Field ownership

| Field | Source | Client may send? |
|---|---|---|
| `code`, `name`, `account_type`, `account_subtype`, `parent_uid`, `is_postable`, `is_active`, `is_cash_equivalent`, `cash_flow_category` | user | yes (subject to §4 rules) |
| `normal_balance` | derived from subtype/type (§5) | **never** |
| `level` | computed from `parent_uid` | **never** |
| `is_locked` | system (join to referencing tables) | **never** |
| `has_transactions` | derived (`EXISTS journal_lines`) | **never** |
| `balance` | derived from ledger | **never** |

> **Frontend adapter note.** The store already keys on `uid` (straight from the
> API). It still maps a few display fields: `type`←`accountType`,
> `active`←`isActive`, `locked`←`isLocked`, and derives `hasTransactions` from
> `balance !== 0` and `contra` from the account. When wiring the real API, map
> these fields and drop the proxies.

---

## 3. Endpoints

### 3.1 List - `GET /accounting/chart-of-accounts`

Returns the full chart. Supports two shapes via `view`:

| Query param | Values | Default | Notes |
|---|---|---|---|
| `view` | `tree` \| `flat` | `tree` | `tree` nests via `children`; `flat` returns an ordered array with `parentUid`/`level`. |
| `search` | string | - | matches `code` or `name`; in `tree` view the server returns matching nodes **plus their ancestors**. |
| `type` | `asset`\|`liability`\|… | - | filter by account type. |
| `active` | `true`\|`false` | - | filter by status. |

Permission: `list_coa`.

**Response (`200`, `view=tree`):**

```json
{
  "data": [
    {
      "uid": "100", "code": "1-000", "name": "Assets · Aset",
      "accountType": "asset", "isPostable": false, "level": 0,
      "balance": 8945200000,
      "children": [
        {
          "uid": "120", "code": "1-100", "name": "Current Assets · Aset Lancar",
          "accountType": "asset", "isPostable": false, "level": 1, "balance": 5120400000,
          "children": [
            { "uid": "128", "code": "1-1003", "name": "Bank Mandiri - 1400xxxx",
              "accountType": "asset", "normalBalance": "debit", "parentUid": "120",
              "level": 2, "isPostable": true, "isActive": true, "isLocked": true,
              "hasTransactions": true, "balance": 1210000000, "children": [] }
          ]
        }
      ]
    }
  ],
  "message": "OK"
}
```

The list is **not paginated** (a chart of accounts is bounded); return the whole
tree. If a flat/paginated variant is ever needed it uses the standard
`{ data: [...], meta: { current_page, last_page, total } }` envelope.

### 3.2 Get one - `GET /accounting/chart-of-accounts/{uid}`

Permission: `list_coa`. Returns a single Account resource (no
`children`). `404` if not found in tenant.

### 3.3 Create - `POST /accounting/chart-of-accounts`

Permission: `create_coa`.

**Request (snake_case):**

```json
{
  "code": "1-1004",
  "name": "Bank BNI - 0099xxxx",
  "account_type": "asset",
  "account_subtype": "cash_bank",
  "parent_uid": "120",
  "is_postable": true,
  "is_cash_equivalent": true,
  "cash_flow_category": "operating"
}
```

Server behavior:

- Derive `normal_balance` from subtype/type (§5). Ignore any client-sent value.
- Compute `level` from `parent_uid`.
- New accounts start `is_active = true`, `has_transactions = false`,
  `is_locked = false`, `balance = 0`.

Validation (`422`): `code` required + unique (per tenant); `name` required;
`account_type` required + one of the enum; `parent_uid` (if present) must exist,
be a **header** (`is_postable = false`) and share the same `account_type` (§4.A).

**Response (`201`):** the created Account resource. `message: "Account saved"`.

### 3.4 Update - `PATCH /accounting/chart-of-accounts/{uid}`

Permission: `update_coa`. Cosmetic and identity fields **except**
`parent_uid` (use §3.5) and the derived/system fields.

**Request (send only changed fields):**

```json
{
  "name": "Bank Mandiri - Operational",
  "code": "1-1003",
  "account_type": "asset",
  "account_subtype": "cash_bank",
  "is_active": true,
  "is_cash_equivalent": true,
  "cash_flow_category": "operating"
}
```

Per-field gating enforced server-side - see the matrix in §4.B. In particular:

- `code`, `account_type` → rejected (`409`) if `has_transactions` or `is_locked`.
- `account_subtype` → free if the new subtype keeps the **same `account_type`
  and `normal_balance`**; otherwise allowed only if `has_transactions = false`.
  On any accepted subtype change, **recompute `normal_balance`** (§5).
- `name`, `is_cash_equivalent`, `cash_flow_category` → always allowed.
- `is_active` handled here or via §3.6; deactivation requires `balance = 0`.
- `is_postable`: leaf→header only if `has_transactions = false`; header→leaf only
  if it has no children.
- `normal_balance`, `level`, `is_locked` in the body → `422` (not client-writable).

**Response (`200`):** the updated resource. `message: "Account saved"`.

### 3.5 Reparent (move) - `POST /accounting/chart-of-accounts/{uid}/move`

Separate endpoint because it moves the whole subtree and recomputes `level`.
Permission: `update_coa` (plus admin when `has_transactions`, see §4.A).

**Request:**

```json
{ "parent_uid": "120" }   // null = move to top level
```

Validation order (reject at the first failure, §4.A):

1. `is_locked` → `403 ACCOUNT_LOCKED`.
2. new parent `account_type` ≠ account's → `422 REPARENT_TYPE_MISMATCH`.
3. new parent `is_postable = true` → `422 REPARENT_ONTO_POSTABLE`.
4. new parent is the account itself or a descendant → `422 REPARENT_CYCLE`.
5. `has_transactions = true` and caller not admin → `403 ACCOUNT_HAS_TRANSACTIONS`
   (or, if the org chooses the safer default, always `409` after transactions -
   the current frontend assumes this stricter default).
6. Apply the move; recompute `level` for the account and all descendants.

**Response (`200`):** the moved account (new `parentUid`/`level`). When it
succeeds while `has_transactions = true`, include
`"warning": "Historical report grouping will shift."`.

### 3.6 Activate / Deactivate

`PATCH /accounting/chart-of-accounts/{uid}/activate` and `.../deactivate`.
Permission: `update_coa`.

- Activate: sets `is_active = true`.
- Deactivate: requires `balance = 0`, else `409 ACCOUNT_HAS_BALANCE`
  ("This account still has a balance. Clear it before deactivating.").

**Response (`200`):** the account. `message: "Account activated"` / `"deactivated"`.

### 3.7 Delete - `DELETE /accounting/chart-of-accounts/{uid}`

Permission: `delete_coa`. Allowed only when the account is empty:

- has children → `409 ACCOUNT_HAS_CHILDREN`.
- `has_transactions = true` or `balance ≠ 0` → `409 ACCOUNT_HAS_TRANSACTIONS`.
- `is_locked` → `403 ACCOUNT_LOCKED`.

**Response (`200`):** `{ "data": null, "message": "Account deleted" }`.

---

## 4. Editability rules (server is authoritative)

### 4.A Reparent (`parent_uid`)

Always enforced: same `account_type`; new parent must be a header
(`is_postable = 0`); no cycles; locked accounts cannot move; moving carries the
whole subtree (recompute `level`). Phase: free before transactions; after
transactions admin-only with a warning (or blocked outright under the safer
default). See §3.5 for the order and error codes.

### 4.B Field matrix

| Field | Editable when |
|---|---|
| `name`, `description` | always |
| `cash_flow_category`, `is_cash_equivalent` | always (warn: reshuffles Cash Flow) |
| `is_active` | always; deactivate needs `balance = 0` |
| `is_postable` | leaf→header only if `!has_transactions`; header→leaf only if no children |
| `parent_uid` | per §4.A |
| `code` | only if `!has_transactions` **and** `!is_locked` |
| `account_type` | only if `!has_transactions` **and** `!is_locked` |
| `account_subtype` | free if same `account_type` + same `normal_balance`; else only if `!has_transactions` |
| `normal_balance` | **never** (derived, §5) |
| `level` | **never** (recomputed on reparent) |
| `is_locked` | **never** (system flag) |

---

## 5. `normal_balance` derivation (hard rule)

`normal_balance` is **always** recomputed server-side on create and on any
subtype change; never accepted from the client. If it could be flipped after
transactions posted, every prior entry against the account would invert its sign.

Base: `asset`, `expense` → **debit**; `liability`, `equity`, `revenue` →
**credit**. Contra subtypes flip:

| Subtype | Type | normal_balance |
|---|---|---|
| Accumulated Depreciation | asset | credit |
| Sales Discount | revenue | debit |
| Owner Drawing | equity | debit |

---

## 6. Error code catalog

| `error_code` | HTTP | UI message |
|---|---|---|
| `ACCOUNT_LOCKED` | 403 | "This is a system account. {field} cannot be changed." |
| `ACCOUNT_HAS_TRANSACTIONS` | 409 | "This account has posted transactions. Its {field} can no longer be changed." |
| `REPARENT_TYPE_MISMATCH` | 422 | "The new group must be the same account type." |
| `REPARENT_ONTO_POSTABLE` | 422 | "You can only move an account under a header, not another posting account." |
| `REPARENT_CYCLE` | 422 | "Cannot move an account under itself." |
| `ACCOUNT_HAS_BALANCE` | 409 | "This account still has a balance. Clear it before deactivating." |
| `ACCOUNT_HAS_CHILDREN` | 409 | "Remove or move its sub-accounts before deleting this group." |
| `LEAF_TO_HEADER_WITH_TXNS` | 409 | "This account already has entries, so it cannot become a header group." |
| `CODE_TAKEN` | 422 | "This account code already exists." (returned under `errors.code`) |

`{field}` is interpolated with the human field name (Account code, Type, …).

---

## 7. Permission matrix

| Action | Endpoint | Permission slug |
|---|---|---|
| View chart | `GET .../chart-of-accounts` | `list_coa` |
| Create account | `POST .../chart-of-accounts` | `create_coa` |
| Edit name/code/type/subtype | `PATCH .../{uid}` | `update_coa` |
| Activate / deactivate | `PATCH .../{uid}/activate|deactivate` | `update_coa` |
| Reparent (no transactions) | `POST .../{uid}/move` | `update_coa` |
| Reparent (after transactions) | `POST .../{uid}/move` | `update_coa` + admin |
| Delete account | `DELETE .../{uid}` | `delete_coa` |

> These slugs are proposals - confirm the exact strings, then add them to each
> route's `meta.permissions` in `src/router/index.js` and gate the row actions
> (reopen/lock-style) in the page.

---

## 8. Status codes summary

| Code | Meaning |
|---|---|
| `200` | OK (read, update, move, activate, delete) |
| `201` | Created |
| `403` | Locked account / missing permission |
| `404` | Account not found in tenant |
| `409` | Business-rule conflict (has transactions / balance / children) |
| `422` | Validation error (`errors` map) or reparent structural violation |
