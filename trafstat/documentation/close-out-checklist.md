# TrafStat — Project Close-Out Checklist

Purpose: this file separates what the code delivers from what the
office must supply, approve, or perform. Read it as an inventory, not
a verdict.

---

## A. Implemented and functional

Confirmed by the tester during Module 8. Every item below exists,
runs, and behaves as documented.

### Public website
- [x] `index.php` — hero, stat cards, feature cards, published
      categories, privacy notice
- [x] `statistics.php` — year selector, monthly trend, category
      doughnut, yearly bar, suppression threshold applied
- [x] `violations.php` — only `is_public = 1` categories with a
      non-empty `public_description`
- [x] `about.php` — purpose, objectives, coverage, privacy notice
- [x] Public footer + nav + mobile drawer
- [x] CSP nonce / no inline scripts without nonce
- [x] No PII in public HTML source (verified in security tests)

### Administrator application
- [x] Login / logout / forced password change
- [x] Login attempt limiting + temporary lockout
- [x] Individual account provisioning via CLI tool
- [x] Dashboard with 5 stat cards, monthly trend, category
      distribution, yearly comparison, recent apprehensions
- [x] Violator CRUD with auto-generated VIO code, search, pagination,
      archive/restore, duplicate warning
- [x] Vehicle CRUD with plate uniqueness, violator linkage, search,
      pagination, archive/restore
- [x] Violation category CRUD with public-disclosure flag and toggle
      activate/deactivate
- [x] Apprehension CRUD with auto-generated APP reference, violator /
      vehicle / category linkage, location text, optional lat/lng,
      archive/restore
- [x] Violation history with repeat-incident indicator and per-violator
      timeline
- [x] Analytics with date-range / category filters, four charts
- [x] Hotspot map with marker clustering (Leaflet + OSM)
- [x] Reports: five report types, filter, preview, print view, CSV
      export
- [x] Activity log viewer with filters and full-chain verification
- [x] Nonce-based CSP, PDO prepared statements, CSRF, escaping,
      security headers, session management, server-side authorization

### Infrastructure / hygiene
- [x] Protected `config/`, `includes/`, `database/`, `documentation/`,
      `tools/`, `storage/` via `.htaccess` and Nginx rules
- [x] CLI-only admin provisioning
- [x] Hash-chained append-only logging
- [x] Single-use download tokens for exports
- [x] Auto-purge of exports (1 hour)
- [x] CSV injection guard
- [x] Source tree free of credentials (`config/config.php` gitignored,
      `config.example.php` committed)

---

## B. Implemented but limited — evaluate before go-live

- [x] **MFA not implemented.** Schema supports TOTP (`mfa_enabled`,
      `mfa_secret`) but no UI or verification flow exists. If the PIA
      requires MFA, this is a bounded addition to schedule (est. 1–2
      developer-days).
- [x] **PDF via browser print-to-PDF**, not a PHP library. Output is a
      valid PDF but styling is browser-driven. If a specific office
      letterhead is required, this needs custom CSS.
- [x] **Excel export is CSV**, not `.xlsx`. Opens correctly in Excel
      with UTF-8 BOM. If pivot tables or multiple sheets are needed,
      adding `PhpSpreadsheet` via Composer is the next step.
- [x] **Field-level encryption not implemented.** Relies on database
      encryption at rest. If the PIA requires field-level encryption
      for contact numbers or other sensitive fields, this is a scope
      addition.
- [x] **Retention/purge is manual.** The DPO's schedule must be
      executed by the administrator. No automatic deletion by design
      (safer than accidental purge).

---

## C. Required before real records may be entered

All items are owned by the office, not by the code.

### Governance
- [ ] DPO designated (or external DPO engaged)
- [ ] Privacy Impact Assessment completed
- [ ] Lawful basis and purpose documented per data type
- [ ] Retention and Disposal Schedule approved
- [ ] Administrator list approved, with roles named
- [ ] Confidentiality obligations signed for every administrator
- [ ] Data subject rights process documented (how a person can
      exercise them)
- [ ] Breach response procedure documented

### Hosting
- [ ] Hosting provider approved by the office
- [ ] Data location (country / region) approved by the DPO
- [ ] Personal Information Processor contract executed with the
      hosting provider (if applicable)
- [ ] Managed database with encryption at rest and TLS approved

### Acceptance
- [ ] `security-testing-report.md` executed and signed
- [ ] `uat-report.md` executed and signed
- [ ] Office head approval to deploy
- [ ] DPO written approval to begin processing real records

---

## D. Optional follow-ups (recommended)

In rough order of impact:

1. **TOTP MFA** — 1–2 developer-days. Two-factor login using
   `mfa_secret` on `administrators`.
2. **Field-level encryption** of `contact_number` (and any other
   field the PIA flags). Requires key-management design; do not
   invent cryptography.
3. **Public hotspot map** with aggregated geographic buckets — only
   if the DPO approves the aggregation method. Design is straightforward;
   publishability is a governance decision.
4. **Server-side `.xlsx` export** via `PhpSpreadsheet` — replaces CSV
   where the office needs multi-sheet or formatted workbooks.
5. **Automated retention purge** — a scheduled job that follows the
   DPO's schedule, logs each purge to `activity_logs`.
6. **Backup verification reminders** — cron-driven alert if the last
   successful restore test is older than 90 days.

None of items 1–6 is required for the office to accept TrafStat as a
records system. Items 1 and 2 are the most likely pre-go-live asks
depending on the PIA.

---

## E. Sign-off

| Role | Name | Signature | Date |
|---|---|---|---|
| Project lead / developer | | | |
| System administrator | | | |
| DPO | | | |
| Office head | | | |

---

## F. What this checklist does NOT claim

- It does **not** claim legal compliance. Compliance is determined by
  the DPO based on the actual processing the office performs.
- It does **not** claim the system is production-ready without Section
  C being complete.
- It does **not** substitute for the signed Security Testing Report and
  UAT Report.
- It does **not** cover CCTV integration, ANPR, external government
  database integration, or any other out-of-scope item from the
  original specification.

The honest summary: the code is complete and defensible. The decision
to process real records is the office's, made with its DPO, informed
by the reports in this module.