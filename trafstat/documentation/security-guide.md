# TrafStat — Security Guide

Purpose: describe the security controls actually implemented in the
TrafStat source, so the office, its auditors, and its DPO can evaluate
them. This is a factual description of engineering, **not a legal
compliance statement.**

---

## 1. Authentication

- **Individual accounts only.** No shared credentials.
- **Password hashing:** PHP `password_hash()` with `PASSWORD_DEFAULT`
  (bcrypt). Automatic re-hash on successful login when the default
  changes.
- **Password policy** (in `functions.php → password_policy_errors()`):
  min 12 chars, at least one uppercase, one lowercase, one digit, one
  special character.
- **Forced first-login change:** `must_change_password = 1` on
  provisioning, enforced by `require_admin_ready()`.
- **Login attempt limiting:**
  - Rate limit by username AND IP over a rolling 15-minute window
  - 5 failures → temporary lockout
  - Failure counters reset on successful login
- **Session ID regeneration:** on login, on logout, on expiry
- **Session revocation:** server-side `admin_sessions` table stores
  `sha256(session_id)`. Logout sets `revoked_at`. Changing password
  revokes every other active session for that account.
- **Idle timeout:** 30 minutes (`SESSION_IDLE_TIMEOUT`)
- **Absolute timeout:** 8 hours (`SESSION_ABSOLUTE_TIMEOUT`)
- **Logout is POST + CSRF.** GET logout is refused with a redirect.

### Multi-factor authentication

The `administrators` schema includes `mfa_enabled` and `mfa_secret`
columns, but the TOTP flow was **not implemented** in this build.
Adding TOTP is a bounded addition (see close-out checklist). Until then,
MFA is **not** available, and the office should evaluate whether
password-only authentication meets its risk tolerance.

---

## 2. Authorization

- **Every authenticated page** starts with `require_admin_ready()`.
- **Every action handler** (`actions/*.php`) starts with
  `require_admin_ready()` and `require_csrf()`.
- **Exports and downloads** additionally require an authenticated
  administrator and a valid single-use token.
- **The public site** never queries individual records; only aggregated
  counts are exposed through the public views.
- **Navigation hiding is not used as a control.** The sidebar and
  topbar reflect the account menu, but the server refuses every
  unauthorized action regardless of what the UI shows.

---

## 3. Session management

- Cookie flags: `HttpOnly`, `SameSite=Lax`, `Secure` in production.
- `session.use_strict_mode = 1` (PHP refuses unknown session IDs).
- `session.use_only_cookies = 1` (no `?PHPSESSID=` in URLs).
- Server-side session registry allows central revocation.

---

## 4. CSRF

- Every state-changing form includes `csrf_field()` (a hidden input
  bound to `$_SESSION['csrf_token']`).
- Every action handler calls `require_csrf()` and returns **HTTP 419**
  on failure.
- Tokens are regenerated on login. Failed token comparison uses
  `hash_equals()`.

---

## 5. SQL injection

- **Every** query uses PDO prepared statements.
- `PDO::ATTR_EMULATE_PREPARES = false` — MySQL native protocol.
- **Placeholders are unique per statement.** No placeholder is bound
  twice. (This was a specific bug fixed during development — see
  `save_violator.php`, `save_vehicle.php`.)
- Table/column interpolation exists only for **whitelisted** identifiers
  (`archive_record.php`, `reports.php`).
- `LIMIT` / `OFFSET` are hard-cast integers before interpolation.

---

## 6. Cross-site scripting

- Every dynamic output goes through `e()` — PHP `htmlspecialchars()`
  with `ENT_QUOTES | ENT_SUBSTITUTE`.
- Chart data uses `json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP |
  JSON_HEX_APOS | JSON_HEX_QUOT`.
- CSV export applies `csv_safe()` to prevent Excel formula injection
  on values starting with `=`, `+`, `-`, `@`, tab, carriage return.
- Inline `onclick` / `onchange` are not used; behavior binds through
  `data-*` attributes and `main.js`.

---

## 7. Content Security Policy

Header set in `send_security_headers()`:

    default-src 'self';
    img-src 'self' data: https://*.tile.openstreetmap.org https://unpkg.com;
    style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com;
    script-src 'self' https://cdn.jsdelivr.net https://unpkg.com 'nonce-<per-request>';
    font-src 'self' https://cdn.jsdelivr.net;
    connect-src 'self';
    frame-ancestors 'none';
    base-uri 'self';
    form-action 'self';

Inline `<script>` blocks carry `nonce="<?= e(csp_nonce()) ?>"`. No
`'unsafe-inline'` in `script-src`. `style-src` includes `'unsafe-inline'`
because Bootstrap 5 relies on inline styles; this is a documented
trade-off, not an oversight.

---

## 8. Security headers

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(self), camera=(), microphone=()`
- `Cross-Origin-Opener-Policy: same-origin`
- `Strict-Transport-Security` (production only)

---

## 9. Error handling

- `display_errors = 0` in production (`APP_ENV = production`).
- All user-facing error responses are generic.
- PDO errors are caught and only the message is logged — no stack
  traces, no SQL, no credentials, no table names.
- Custom 419 for CSRF failure. No framework default error pages.

---

## 10. Personal information controls

- **Public pages never display or query individual records.** Only
  aggregated statistics and approved category descriptions.
- **Suppression threshold** (`PUBLIC_SUPPRESSION_THRESHOLD`) is applied
  to public bucket counts. Buckets below threshold become `null`
  (a gap in the chart) or are merged into "Other (aggregated)".
- **Logs** never store passwords, session secrets, contact numbers,
  or addresses. They do store `admin_id`, event type, related record
  ID, IP, and a short description. `details` JSON is used sparingly.
- **Exports** go through `storage/exports/` (denied by `.htaccess` and
  Nginx), are named with a 64-hex random token, and are auto-deleted
  after streaming or after 1 hour.

### Encryption

- **In transit:** HTTPS required in production. Database TLS required.
- **At rest:** relies on the managed MySQL provider's encryption and
  any OS-level encryption on the app server.
- **Field-level encryption is not implemented.** If the DPO's PIA
  requires it for specific fields (e.g. contact number), this is a
  scope addition to schedule before go-live.

---

## 11. Logging and monitoring

- `activity_logs` records every mutation, login, logout, failed
  login, export, download, and verification.
- Each entry carries a `previous_hash` and an `entry_hash` forming a
  SHA-256 chain. The **Verify chain integrity** button recomputes the
  entire chain and reports breaks.
- **Tamper-evident, not tamper-proof.** An attacker with write access
  and knowledge of the algorithm could forge a new valid chain. The
  chain detects casual edits and deletions; it is not a substitute for
  write-once storage or external attestation.
- Chain verification is itself logged (`ACTIVITY_LOG_VERIFIED`).

---

## 12. Known limitations (honest list)

| Item | Status |
|---|---|
| TOTP multi-factor authentication | Schema supports it; **not implemented** |
| Field-level encryption of sensitive fields | **Not implemented** |
| PHP-side PDF library | Uses browser print-to-PDF instead |
| PHP-side Excel (.xlsx) library | Uses CSV (Excel-compatible) |
| Automated retention/purge | Manual; DPO-set schedule |
| Email/SMS password reset | Deliberately **not implemented** |
| Public hotspot map | Deliberately **not published** — admin-only |
| CCTV / ANPR integration | Out of scope |
| Mobile apps | Out of scope |

None of these is a defect. Each is an explicit scope decision to be
confirmed with the office. Adding MFA and field-level encryption are
the two most likely pre-go-live additions depending on the PIA.

---

## 13. Incident response

1. **Detect.** Chain verify failure, anomalous login attempts, report
   of unauthorized access, or unusual export activity.
2. **Contain.** Deactivate affected accounts (`account_status =
   'inactive'`), revoke their sessions.
3. **Preserve.** Take a database snapshot before any corrective action.
4. **Notify the DPO immediately.** The DPO decides whether this is a
   personal-data breach requiring NPC notification within 72 hours.
5. **Investigate.** Correlate `activity_logs`, `login_attempts`, web
   server logs, database slow query logs.
6. **Remediate.** Fix the root cause. Do not delete evidence.
7. **Document.** Record in the office's incident register.

TrafStat's code supports this process. It does not substitute for the
office's written incident response plan, which the DPO owns.