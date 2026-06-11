# Handover — Centralized RS256 JWT Auth Migration

> Continuation doc so this work can resume on another machine.
> Last updated: 2026-06-11. Author: Zola + Claude Code.

---

## 0. TL;DR

Migrating the ERP from per-service auth (login chains Laravel → Express/Python to mint tokens)
to **Laravel as the sole RS256 JWT issuer**; every other service verifies the access token
**locally** with the public key (no per-request callback). Long-lived **opaque** refresh tokens
live in Laravel's DB with rotation + family theft detection.

**Pass 1 (Laravel issuer + refresh layer) is DONE** in this repo (`erp-backend`), uncommitted.
**Pass 2 (the verifiers + Vue frontend) is NOT started.**

---

## 1. Decisions already locked (do not re-litigate)

| Decision | Choice |
|---|---|
| Rollout | **Staged / dual-issue** — Laravel issues the new token AND keeps the old token fields working during transition. Remove the legacy chained login LAST. |
| Keypair | **New dedicated keypair** (separate from the existing MCP/OAuth keys), loaded via base64 env vars `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY`, one keypair per environment. |
| Order | **Laravel first** (done), then verifiers + frontend. |
| Scope | erp-backend, erp-backend-node, whatsapp, erp-frontend. **erp-office and erp-mcp NOT in scope.** |
| **erp-report** | **EXCLUDED — do not modify the Python service** (explicit user instruction). |

Fixed design params: access TTL **30 min**; refresh **1 day / 30 days (remember)**, **sliding window**;
permissions claim = **flat array of strings**; algorithm **RS256**; menu served from `/api/menu`, NOT in the token.

---

## 2. Access token shape (the contract every verifier must implement)

RS256 JWT, header `{ "alg": "RS256", "kid": "key-1" }`, claims:

```
iss          = https://auth.dfactory.pro   (config jwt.issuer)
aud          = erp                          (config jwt.audience)
sub          = <user id>                    (Laravel users.id, NOT email)
jti          = <uuid>
iat, exp     = now / now + 30 min
roles        = ["root", ...]               (role names)
permissions  = ["deal.create","lead.read"] (flat strings)
```

Refresh token = opaque `Str::random(64)`, delivered as **httpOnly cookie** `df_refresh_token`;
access token returned in the JSON body.

New endpoints:
- `POST /api/auth/login`   → body `{ access_token, ...legacy fields }` + sets refresh cookie
- `POST /api/auth/refresh` → rotates, returns `{ access_token }` + new cookie; 401 if invalid/expired/revoked
- `POST /api/auth/logout`  → revokes refresh token + clears cookie
- `GET  /api/menu`         → token-verified (middleware `jwt.auth`), menu built fresh from permissions

---

## 3. What Pass 1 added (this repo, `erp-backend`)

New files:
- `config/jwt.php` — keys (base64-decoded), iss/aud/kid, TTLs, cookie settings.
- `app/Console/Commands/JwtGenerateKeys.php` — `php artisan jwt:keygen`.
- `app/Console/Commands/PruneRefreshTokens.php` — `auth:prune-refresh-tokens` (scheduled daily 02:00).
- `app/Services/Auth/TokenService.php` — `issueAccessToken(User)` + verification `Configuration`.
- `app/Services/Auth/RefreshTokenService.php` — issue / rotate / revoke / revokeFamily / cookie helpers.
- `app/Models/RefreshToken.php`
- `app/Exceptions/Auth/RefreshTokenInvalid.php`
- `app/Http/Middleware/AuthenticateWithAccessToken.php` (alias `jwt.auth`)
- `app/Http/Controllers/Api/Auth/AuthTokenController.php` (`refresh`)
- `database/migrations/2026_06_11_000001_create_refresh_tokens_table.php`
- `tests/Feature/Auth/CentralizedAuthTest.php`

Edited files:
- `app/Http/Controllers/Api/Auth/LoginController.php` — login now dual-issues (adds `access_token` + cookie); logout revokes refresh + clears cookie.
- `app/Http/Controllers/Api/MenuController.php` — added `userMenu()`.
- `routes/api.php` — `POST auth/refresh`, `GET menu` (jwt.auth).
- `routes/console.php` — prune schedule.
- `bootstrap/app.php` — `jwt.auth` alias.
- `.env.example`, `.gitignore` — JWT vars + ignore `storage/jwt`.

NOTE: `app/Services/AuthService.php` and `app/Data/Auth/PayloadLoginData.php` are **pre-existing stubs**
(created before this work) — left untouched.

---

## 4. ⚠️ Status of tests — NOT run yet

The dev shell used for Pass 1 **cannot bootstrap Laravel**: `AppServiceProvider::boot()` calls
`cachingSetting()`, the app defaults to `pgsql` (driver not installed there) and there was no `.env`.
So `artisan` / `php artisan test` could not run locally. All files are `php -l` clean and `vendor/bin/pint` clean.

**On a machine with the real DB env, run:**
```bash
php artisan migrate
php artisan test --filter=CentralizedAuthTest
```
Test DB is `erp_testing_new` (see phpunit.xml). The tests self-generate a throwaway RSA keypair via
`config()->set('jwt.*', ...)` in `beforeEach`, so they don't need real keys.

The login integration test fakes the two external HTTP calls and is the most environment-sensitive
(touches settings/employee helpers) — adjust if it trips on incidental data.

---

## 5. Setup on the other machine

```bash
# 1. get the code (commit/push first if not already)
cd app/erp-backend
git checkout feat/centralized-rs256-auth   # or whatever branch you pushed

# 2. generate a keypair for THIS environment (never reuse across envs)
php artisan jwt:keygen
#   → paste the printed JWT_PRIVATE_KEY / JWT_PUBLIC_KEY into .env
#   → distribute ONLY JWT_PUBLIC_KEY to the verifier services

# 3. migrate + test
php artisan migrate
php artisan test --filter=CentralizedAuthTest
```

Also set in `.env` (see `.env.example` block): `JWT_ISSUER`, `JWT_AUDIENCE`, TTLs, and the cookie vars.
For a cross-site frontend (frontend domain ≠ backend.dfactory.pro): set
`JWT_REFRESH_COOKIE_SAME_SITE=none`, `JWT_REFRESH_COOKIE_SECURE=true`,
`JWT_REFRESH_COOKIE_DOMAIN=.dfactory.pro`.

---

## 6. Bug already fixed (context, no action needed)

In `RefreshTokenService::rotate()` the theft-path `revokeFamily()` originally ran INSIDE
`DB::transaction()` immediately before `throw` — the throw rolled the revocation back, defeating
theft detection. Fixed: the replay is detected inside the transaction but the family revocation +
throw happen AFTER it commits. Keep this property if you refactor.

---

## 7. Pass 2 — remaining work (NOT started)

### 7a. erp-backend-node (Express + Bun) — verify locally with `jose`
- Already has `jose ^6` and an RS256 reference: `src/core/middleware/mcpBearerToken.ts` (reads a public key, verifies iss/aud). Copy that pattern.
- Current primary auth is HS256: `src/core/middleware/bearerToken.ts` (uses `JWT_SECRET`); token mint in `src/modules/hrd/services/auth.service.ts`; the chained login endpoint Laravel calls is `POST /api/v2/hrd/auth/login`.
- **Plan:** add a middleware that verifies the new access token with the public key (base64 env `JWT_PUBLIC_KEY`), checks `iss=https://auth.dfactory.pro`, `aud=erp`, `exp`; attach claims to `req.user`.
- **GOTCHA:** the new token `sub` = Laravel **user id**, but the existing permission layer (`src/core/middleware/permission.ts` + `src/core/services/permission.service.ts`, DB + Redis) keys off **email** in `req.user.id`. Follow `mcpBearerToken.ts`: after verifying, look up the local user by id and set `req.user.id = user.email` so permission checks keep working. (Or enforce straight from the token's `permissions` claim.)
- Staged: keep `/api/v2/hrd/auth/login` until Laravel stops calling it.
- `.env.example`: add `JWT_PUBLIC_KEY`.

### 7b. whatsapp (Express, CommonJS) — add `jose`
- No JWT libs today; auth is HMAC-only (`src/middleware/hmacheader.js`) on `/api/message/*`.
- Add `jose` (works via `require()` in CJS), add a verify middleware mirroring the contract above.
- **OPEN QUESTION:** whatsapp routes are service-to-service (HMAC-signed). Decide whether any route is actually user-facing and needs the JWT, or whether HMAC stays for internal calls and JWT is only for user-initiated ones. Confirm with Zola before wiring.
- `.env.example` (none tracked today): add `JWT_PUBLIC_KEY`.

### 7c. erp-frontend (Vue / Pinia) — the biggest piece
- Login: `src/pages/auth/LoginPage.vue` → `src/stores/authentication.js` (`login()` posts to `/auth/login`, stores tokens in localStorage: `dfauthmain`, `dfauth`, `pEnc`, `mEnc`, `etoken`, `menus`).
- Axios: `src/plugins/index.js` (request interceptor sets `Bearer` from `dfauthmain`; response interceptor HARD logs out on 401 — no refresh). Second client `src/plugins/axiosClient.js` (`VITE_EXPRESS_URL`). Token validity helper `src/compose/tokenSetter.js`.
- Perms today: `src/compose/checkPermission.js` (reads `pEnc`), `src/compose/getRole.js`, `src/compose/breakToken.js` (crypto-js AES, shared `VITE_SALT_KEY`). No `v-can`. Router guard in `src/router/index.js` `beforeEach` (decrypts `dfauth`, checks exp).
- No `jwt-decode` installed.
- **Plan:**
  1. `npm i jwt-decode`.
  2. Rework the Pinia `auth` store: `setToken(accessToken)` decodes the JWT (decode ≠ verify, no key), stores `permissions` + `roles`; getters `can(permission)` / `hasRole(role)`. Call `setToken()` after login AND inside the refresh flow.
  3. Axios response interceptor: on a service 401, silently `POST /auth/refresh` ONCE, swap the new access token, retry the original request. Dedupe concurrent 401s with a shared refresh promise. If the refresh call itself 401s → redirect to login. The refresh request must NOT re-enter the retry interceptor (avoid infinite loop). Set `withCredentials: true` so the refresh cookie is sent.
  4. `v-can` directive + router `beforeEach` using `auth.can()` with `to.meta.permission` (UX gating only — backend is the real gate).
- Cookie cross-site: see §5 (SameSite=None/Secure/domain) + axios `withCredentials`.

### 7d. erp-backend (final cutover, AFTER all consumers verify the new token)
- Remove the chained calls `authorizeExpressAccess()` / `authorizeReportingAccess()` in `app/Services/GeneralService.php` and the legacy token fields from the login response → satisfies acceptance criterion #1.
- Optional: publish a JWKS for the NEW auth keypair (existing `/.well-known/jwks.json` serves the MCP/OAuth key) to enable `kid`-based rotation.

---

## 8. Acceptance criteria checklist

- [x] Login returns both tokens (legacy login removal pending — staged §7d)
- [ ] Each service authorizes by verifying the access token locally (Pass 2)
- [ ] Expired access token silently refreshed in Vue (Pass 2)
- [x] Refresh rotates; replay revokes family; rapid double-refresh = no false theft *(implemented; verify via tests in real DB)*
- [x] Remember = 30-day session surviving rotations; else 1 day *(implemented; verify via tests)*
- [x] Tampered payload fails verification *(implemented; verify via tests)*
- [x] Per-environment keypair; no private key committed

---

## 9. Loose ends / heads-up

- **Security:** the `erp_workspace` (erp-infra) git remote has a GitHub PAT embedded in plaintext (`git remote -v` shows `ghp_…`). Rotate it; switch to SSH or a credential helper.
- Memory files written for this work: `centralized-auth-migration.md`, `no-changes-erp-report.md` (under `~/.claude/projects/-var-www-apps-dfactory-erp-workspace/memory/`). Only present on the other machine if `~/.claude` is synced.
- This shell couldn't run artisan/tests (see §4) — that's an env limitation, not a code problem.
