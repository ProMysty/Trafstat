# TrafStat — Security Testing Report

**Status:** Template — completed by the tester(s) named below.
**Instructions:** replace every `<…>` placeholder with the actual value
observed during testing. Do not mark PASS without executing the test.

---

## 0. Summary

| Field | Value |
|---|---|
| Report date | <YYYY-MM-DD> |
| Tester(s) | <name(s)> |
| Environment | <local / staging / pre-prod> |
| Application version | <git commit hash or tag> |
| PHP version | <x.y.z> |
| MySQL version | <x.y.z> |
| Overall result | <PASS / PASS WITH NOTES / FAIL> |
| Signature | <name / role / date> |

---

## A. Functional & access control

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| A1 | Anonymous GET `/admin/dashboard.php` | Redirect to login | | ☐ |
| A2 | Anonymous GET `/admin/violators.php` | Redirect to login | | ☐ |
| A3 | Anonymous GET `/admin/reports.php` | Redirect to login | | ☐ |
| A4 | Anonymous GET `/admin/activity_logs.php` | Redirect to login | | ☐ |
| A5 | Anonymous GET `/actions/save_violator.php` | Redirect to login | | ☐ |
| A6 | Anonymous GET `/admin/download_export.php?token=<64hex>` | Redirect to login | | ☐ |
| A7 | Logged-in admin, `must_change_password = 1`, GET `/admin/dashboard.php` | Redirect to `change_password.php` | | ☐ |
| A8 | Logged-in admin, edit a violator, save | 200 + success flash + `VIOLATOR_UPDATED` in logs | | ☐ |
| A9 | Logged-in admin, force `violator_id = 999999` via URL | Error flash, no DB change | | ☐ |

---

## B. Authentication

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| B1 | Correct username + wrong password | Generic "Invalid username or password" + `login_attempts` row + `LOGIN_FAILED` | | ☐ |
| B2 | 5 wrong passwords in 15 minutes | 6th attempt blocked by rate limit | | ☐ |
| B3 | Correct login | Session regenerated; `admin_sessions` row inserted; `LOGIN_SUCCESS` | | ☐ |
| B4 | Logout via POST button | Session destroyed; `admin_sessions.revoked_at` set; `LOGOUT` entry | | ☐ |
| B5 | Logout via GET | Redirected to dashboard with info flash; session intact | | ☐ |
| B6 | Change password, confirm strong | `must_change_password = 0`, other sessions revoked, `PASSWORD_CHANGED` | | ☐ |
| B7 | Change password to previously-used password | Rejected with policy message | | ☐ |
| B8 | Idle > 30 min, click any admin link | Session expired, redirected to login with inactivity message | | ☐ |
| B9 | Account status set to `inactive`, attempt login | Rejected | | ☐ |

---

## C. CSRF

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| C1 | POST without `csrf_token` to `/actions/save_violator.php` | HTTP 419 | | ☐ |
| C2 | POST with garbage `csrf_token` | HTTP 419 | | ☐ |
| C3 | Cross-origin form submit to any action | HTTP 419 | | ☐ |
| C4 | CSRF token from another session | HTTP 419 | | ☐ |

---

## D. SQL injection

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| D1 | Search `q` = `' OR 1=1 -- ` | No results, no error | | ☐ |
| D2 | Violator `first_name` = `Robert'); DROP TABLE traffic_violators;--` | Stored literally, table intact | | ☐ |
| D3 | Login username = `' OR '1'='1` | Login rejected | | ☐ |
| D4 | Report `type` param = `x'; DROP TABLE activity_logs;--` | Rejected, DB intact | | ☐ |
| D5 | `archive_record.php` `entity=violator'` | Rejected | | ☐ |

---

## E. XSS

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| E1 | Violator `first_name` = `<script>alert(1)</script>` | Rendered as text, no alert | | ☐ |
| E2 | Violator address = `" onmouseover="alert(1)` | Rendered as text | | ☐ |
| E3 | Report `location` field, victim = `</td><script>alert(1)</script>` | Rendered as text | | ☐ |
| E4 | Category public description = `<img src=x onerror=alert(1)>` | Rendered as text on public site | | ☐ |
| E5 | CSV export of a `=cmd` value | Prefixed with `'` in the file | | ☐ |

---

## F. Session

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| F1 | Inspect `Set-Cookie` at login | `HttpOnly`, `SameSite=Lax`, `Secure` (production) | | ☐ |
| F2 | Session ID does not appear in URL after login | Confirmed | | ☐ |
| F3 | Login as Admin B in a second browser; Admin A changes password | Admin B's session is revoked on next request | | ☐ |
| F4 | Copy Admin A's session cookie to a different browser | Works only if not revoked; verifies with `admin_sessions` | | ☐ |
| F5 | Manually set `TRAFSTAT_SID=<random>` | Rejected | | ☐ |

---

## G. Headers / CSP

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| G1 | Response includes `Content-Security-Policy` | Yes, with `nonce-` | | ☐ |
| G2 | `X-Content-Type-Options: nosniff` | Present | | ☐ |
| G3 | `X-Frame-Options: DENY` | Present | | ☐ |
| G4 | Production HTTP redirects to HTTPS | 301 | | ☐ |
| G5 | Production includes `Strict-Transport-Security` | Present | | ☐ |
| G6 | Console: no CSP violations on admin pages | Clean | | ☐ |
| G7 | Console: no CSP violations on public pages | Clean (except DevTools-only source-map warnings) | | ☐ |

---

## H. Exports

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| H1 | Export CSV, check `storage/exports/` after download | Empty | | ☐ |
| H2 | Export CSV, copy download URL, open again | 404 | | ☐ |
| H3 | Anonymous GET `/storage/exports/` | 403 | | ☐ |
| H4 | Token with wrong format (`?token=abc`) | 400 | | ☐ |
| H5 | `REPORT_EXPORTED` and `REPORT_DOWNLOADED` in `activity_logs` | Present | | ☐ |
| H6 | Two-admin scenario: Admin A's download link, Admin B tries | 404 | | ☐ |

---

## I. Activity log chain

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| I1 | Verify chain on untouched log | Green | | ☐ |
| I2 | Modify one `action_description` via DB, verify | Red, `log_id` identified | | ☐ |
| I3 | Restore row, verify | Green | | ☐ |
| I4 | Delete one middle row via DB, verify | Red, `previous_hash` mismatch | | ☐ |
| I5 | Verify action itself logged | `ACTIVITY_LOG_VERIFIED` present | | ☐ |

---

## J. Public privacy

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| J1 | View source of every public page | No `first_name`, `last_name`, `plate`, `address`, `contact`, `apprehension_id` | | ☐ |
| J2 | `window.PUBLIC_STATS` JSON | Only labels + integers | | ☐ |
| J3 | Network tab: any `admin/*` or `actions/*` request from a public page | None | | ☐ |
| J4 | Violation category with `is_public = 0` | Not shown on public site | | ☐ |
| J5 | Bucket below `PUBLIC_SUPPRESSION_THRESHOLD` | Omitted or merged; not shown individually | | ☐ |
| J6 | Public hotspot map | Does not exist | | ☐ |
| J7 | Curl: `grep -i 'first_name\|last_name\|plate'` on public HTML | Empty | | ☐ |

---

## K. Configuration exposure

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| K1 | `GET /config/config.php` | 403 | | ☐ |
| K2 | `GET /tools/create_admin.php` | 403 (dev) / deleted (production) | | ☐ |
| K3 | `GET /database/trafstat.sql` | 403 | | ☐ |
| K4 | `GET /includes/init.php` | 403 or no sensitive output | | ☐ |
| K5 | `GET /documentation/` (production) | 403 | | ☐ |

---

## L. Backup / recovery

| # | Test | Expected | Actual | Result |
|---|---|---|---|---|
| L1 | Take a full backup | File created, encrypted | | ☐ |
| L2 | Restore to staging | Row counts match source | | ☐ |
| L3 | Verify chain after restore | Green, or `truncation_warning` for partial restore | | ☐ |
| L4 | Login as administrator after restore | Works | | ☐ |
| L5 | Time-to-restore | Within RTO (4 h) | | ☐ |

---

## Findings summary

| Severity | Count | Notes |
|---|---|---|
| Critical | | |
| High | | |
| Medium | | |
| Low | | |

**Overall determination:** <PASS / PASS WITH NOTES / FAIL>

**Recommendations for follow-up before go-live:**

- <e.g. "Implement TOTP MFA if PIA requires">
- <e.g. "Field-level encryption of contact_number if PIA requires">

**Tester signature:** _______________________ **Date:** ___________

**DPO acknowledgement:** _______________________ **Date:** ___________