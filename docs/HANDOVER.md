# Handover — HRD document management: work contract letters (end date)

Date paused: 2026-09-15 · Branch: `staging` · Repo: `erp_workspace/app/erp-backend`

## Goal in one line
Make the signature/document-management feature able to produce **work contract letters**, which
need a **contract end date** placeholder (`${end_date}`). `${join_date}` and everything else already work.

---

## DECISION MADE (implement this next)
Model the contract end date with a dedicated **`employee_contracts` table** (option B), NOT a flat
`employees.contract_end_date` column. Rationale chosen by the developer: proper domain model with
contract periods + renewal/history; the **active** contract feeds `${end_date}`.

### Why a flat column was rejected
`employees.end_date` already exists but is the **resignation / termination date** (set on resign,
`null` for active staff) — confirmed via `EmployeeService::createEmployeeV2` neighbours and the
resign flow (`'end_date' => $resignDate`, added in the same migration as `resign_reason`). It is NOT
a contract expiry. Do not reuse it.

### Proposed shape for `employee_contracts` (confirm details with dev before building)
- `id`, `uid` (auto via ModelObserver trait, see PositionBackup for the pattern)
- `employee_id` (FK employees)
- `start_date` (date) — contract start (often = employee join_date for the first contract)
- `end_date` (date, nullable) — contract expiry  ← this feeds `${end_date}`
- `employment_status_id` (FK employment_statuses) — the contract's status (PERM/CONTRACT/…)
- `status`/`is_active` flag or derive "active" as the latest non-expired row
- `notes` (nullable), `created_by`, timestamps, softDeletes
- Model `Employee` gets `contracts(): HasMany` and `activeContract(): HasOne` (latest / not-ended).

### Wiring the placeholder (this is the important part)
`${end_date}` currently maps to `employees.end_date` in `config/signature.php` — **change this** to
resolve from the active contract. The signature generation resolves placeholders in
`SignatureService::getDocumentColumnsReplacer()` + `createEmployeeDocument()`:
- Direct columns come from `available_replacer_column[*].column`.
- Relation placeholders (like `employee_position_name` → `position.name`) were just added and use
  `available_replacer_column[*].relation` + a `path` (e.g. `position.name`), resolved on-demand via
  `Employee::whereKey(...)->with([relation])` then `data_get()`.
- So `end_date` should become a **relation-based** entry, e.g.
  `'relation' => 'activeContract:id,employee_id,end_date'`, `'column' => 'end_date'`,
  path `activeContract.end_date`. Reuse the exact mechanism added for `employee_position_name`
  (see `registerEmployeeOnGreatday`/`getDocumentColumnsReplacer` diff in SignatureService).
- Update `tests/Feature/Hrd/SignatureGenerateDocumentPositionTest.php` style to add an end_date case,
  and `tests/Feature/Hrd/SignatureDetectPlaceholderTest.php` already asserts `end_date` is a known
  placeholder — keep that green (it currently checks the config key exists).

### Also decide
- Should `employee_contracts` be populated on employee create (v1 `Create` + new v2 endpoint) and on
  the update form? For contract employees the first contract likely mirrors join_date + a chosen end.
- The v2 create payload (mirrors Node) has NO end date field, so contract creation is likely a
  separate action / a nullable add to the forms. Confirm with dev.

---

## WORK ALREADY DONE THIS SESSION (uncommitted on `staging`)

### 1. `${end_date}` recognised as a placeholder (config only, so far)
- `config/signature.php`: added `end_date` entry (currently `model => Employee, column => end_date`).
  ⚠️ This points at the WRONG column (resignation date). When implementing `employee_contracts`,
  repoint it to the active-contract relation as described above.

### 2. `${employee_position_name}` now actually fills (was a real bug)
- `SignatureService::getDocumentColumnsReplacer()` used to DROP every relation-based placeholder
  (`! isset($item['relation'])`), so position never rendered.
- Fix: `getDocumentColumnsReplacer()` now also returns a `relations` array; `createEmployeeDocument()`
  resolves each relation on-demand and `setValue`s it; both callers (`generateDocument`,
  `bulkGenerateDocument`) pass `relations` through. **This is the mechanism to reuse for end_date.**
- Note: placeholder replacement happens at GENERATION time and is baked into the stored file.
  The `render` endpoint (`/api/signatures/file/employee/{uid}/render`) only overlays signatures — it
  does NOT re-resolve data placeholders. So existing/old generated docs won't self-heal; regenerate.

### 3. New Laravel endpoint: `POST /api/v2/hrd/employees` (faithful port of erp-backend-node)
Mirrors `erp-backend-node` `POST /api/v2/hrd/employees` (`createEmployeeSchema` + `createEmployee`).
- Route: `Modules/Hrd/routes/api.php` (group `prefix('v2/hrd')` + `auth.session`), name
  `api.employees.v2.store`. Full URL (local): `http://backend.localhost:8087/api/v2/hrd/employees`.
- Request: `Modules/Hrd/app/Http/Requests/Employee/CreateEmployeeV2.php` (same fields as the Node zod schema).
- Controller: `EmployeeController::storeV2`.
- Service: `EmployeeService::createEmployeeV2()` + helper `registerEmployeeOnGreatday()`.
  - Resolves employment_status BY CODE, position BY uid, supervisor BY uid; uniqueness on employee_no/email.
  - Builds employee (name=first+middle+last, nickname stripped+lowercased, phone `+` stripped,
    hardcoded `religion=islam`/`martial=single`, gender `'1'`→male, bank_detail JSON, raw greatday_* fields,
    `basic_salary=0`, `salary_type=1`, `is_phone_verified=0`).
  - IMPORTANT MAPPING (confirmed with dev): `employment_status_id => $employmentStatus->id` (FK), and
    `status => Status::Permanent->value` (the Employee `Status` enum, NOT the FK id). Node conflates
    these two; Laravel keeps them separate.
  - `invite_on_erp` → `UserService::mainServiceStoreUser()` (creates user + role + user_id link + activation email).
  - `register_on_greatday` → reuses `GreatdayService::addEmployee()` + `buildGreatdayAddPayload()` +
    resolves `greatday_emp_id` via `fetchAllGreatdayEmployees()`.
- Tests: `tests/Feature/Hrd/CreateEmployeeV2Test.php` (11 passing) — service + e2e, UserService/GreatdayService mocked.

### Files touched (git status at pause)
```
 M Modules/Hrd/app/Http/Controllers/Api/EmployeeController.php   (storeV2 + import)
 M Modules/Hrd/app/Services/EmployeeService.php                  (createEmployeeV2 + registerEmployeeOnGreatday)
 M Modules/Hrd/app/Services/SignatureService.php                 (relation-placeholder resolution)
 M Modules/Hrd/routes/api.php                                    (v2/hrd group)
 M config/signature.php                                          (end_date + already-present join_date)
?? Modules/Hrd/app/Http/Requests/Employee/CreateEmployeeV2.php
?? tests/Feature/Hrd/CreateEmployeeV2Test.php
?? tests/Feature/Hrd/SignatureDetectPlaceholderTest.php
?? tests/Feature/Hrd/SignatureGenerateDocumentPositionTest.php
```

---

## HOW TO RUN TESTS (this environment, outside Docker)
Default DB connection resolves to pgsql (no driver here); force MySQL + array cache. Auth-guarded
routes (`auth.session`) need JWT keys at runtime. See memory files `erp-backend-test-run` and
`erp-backend-pint-churn`.

```bash
KEY="base64:$(head -c 32 /dev/urandom | base64)"
PRIV=$(openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 2>/dev/null)
PUB=$(printf '%s' "$PRIV" | openssl rsa -pubout 2>/dev/null)
JPRIV=$(printf '%s' "$PRIV" | base64 -w0); JPUB=$(printf '%s' "$PUB" | base64 -w0)

DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=erp_testing_new \
  DB_USERNAME=erp_test DB_PASSWORD='Password123!' CACHE_STORE=array \
  APP_KEY="$KEY" JWT_PRIVATE_KEY="$JPRIV" JWT_PUBLIC_KEY="$JPUB" \
  php artisan test tests/Feature/Hrd/CreateEmployeeV2Test.php
```
- New migrations must be applied to the test DB once: same env + `php artisan migrate --force`.
- The developer's normal flow is `make test` inside Docker (no Makefile exists in this repo copy).

## GOTCHAS
- **Do NOT run `vendor/bin/pint --dirty` blanket.** It reformats huge legacy files (EmployeeService,
  SignatureService, ProjectService) with hundreds of unrelated changes. Pint only the NEW files you
  authored (requests/tests). Additions in legacy services were left unpinted on purpose.
- **Octane/FrankenPHP**: new routes/service code won't take effect on the running backend until
  workers reload/restart.
- No em-dashes in code/docblocks (developer preference).
- Keep placeholder resolution DRY: end_date should reuse the relation mechanism, not a new code path.

## IMMEDIATE NEXT STEPS
1. Confirm the `employee_contracts` schema details above with the developer (fields, "active" rule,
   whether it is set at employee create/update).
2. Migration + `EmployeeContract` model + `Employee::contracts()` / `activeContract()`.
3. Repoint `config/signature.php` `end_date` to the active-contract relation (path `activeContract.end_date`).
4. Verify `SignatureService::getDocumentColumnsReplacer()` handles the new relation (it should, generically).
5. Tests: add an end_date generation case (mirror the position test), keep detect-placeholder green.
6. Decide how contracts get created (form/endpoint) and whether the v2 create should accept a contract.
