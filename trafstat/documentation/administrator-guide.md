# TrafStat — System Administrator Guide

Audience: the office's designated TrafStat system administrator and the
Data Protection Officer (DPO). This document covers operational
responsibilities the user guide does not.

---

## 1. Responsibilities

The TrafStat System Administrator is responsible for:

- Provisioning and de-provisioning individual administrator accounts
- Performing password resets when the secure process is followed
- Verifying the activity log hash chain on a defined schedule
- Monitoring login attempts and failed-login patterns
- Ensuring the backup/recovery procedure is actually executed
- Coordinating with the DPO on retention, disposal, and incident response
- Keeping the software current with supported PHP/MySQL versions

This role does **not** extend to granting legal approval, publishing
public statistics, or interpreting privacy law. Those require the DPO.

---

## 2. Provisioning a new administrator

    /opt/lampp/bin/php tools/create_admin.php <username> \
      "<Full Name>" <email>

Rules:

- Each administrator gets their **own** account. There are no shared
  logins.
- Deliver the printed temporary password to the new administrator
  through an approved channel (in person, or an encrypted message
  channel the office has approved). Never by SMS or plain email.
- The account is forced to change its password on first login.
- Record the provisioning action in the office's personnel authorisation
  register.

**De-provisioning:** set `account_status = 'inactive'` in the database
(not delete — activity logs must remain intact), and revoke any active
sessions:

    UPDATE administrators SET account_status = 'inactive' WHERE admin_id = ?;
    UPDATE admin_sessions SET revoked_at = NOW()
     WHERE admin_id = ? AND revoked_at IS NULL;

Then verify the account can no longer log in.

---

## 3. Password resets

The secure reset procedure:

1. Verify the person's identity in person or through an approved channel
   the office documents in its access policy.
2. Use the CLI reset tool (see the user guide module's extension) or run:

        UPDATE administrators
           SET password_hash = '<generated-here>',
               must_change_password = 1,
               failed_login_attempts = 0,
               locked_until = NULL
         WHERE admin_id = ?;

   where `<generated-here>` is the PHP `password_hash()` output produced
   by a fresh script.

3. Never implement "security questions", SMS-based reset, or email-link
   reset without the DPO's explicit approval and a documented flow.

---

## 4. Verifying the activity log chain

Run this on a defined schedule (recommended: **daily** and after any
unusual activity):

1. Log in as an administrator with access to Activity Logs.
2. Open **Activity Logs**.
3. Click **Verify chain integrity**.
4. Confirm the green success banner appears.

If you see a **red** failure:

1. **Do not delete or modify anything.**
2. Note the `log_id` where the chain broke.
3. Capture a screenshot and the current system time.
4. Notify the DPO immediately.
5. Preserve a copy of the database as evidence.
6. Follow the office's incident-response procedure.

The chain verify action is itself logged with `ACTIVITY_LOG_VERIFIED`.

---

## 5. Monitoring

At least weekly, review:

- `login_attempts` — recurring failures for the same username or IP may
  indicate an attempt to guess passwords.
- `activity_logs` filtered by `REPORT_EXPORTED` / `REPORT_DOWNLOADED` —
  confirm exports were for legitimate operational use.
- The `admin_sessions` table — confirm sessions are being revoked on
  logout. Un-revoked sessions older than 8 hours indicate a problem.

Log review should be documented in the office's operations record.

---

## 6. Backups

See `backup-recovery.md`. Non-negotiables:

- Backups run daily and are encrypted.
- Backups are stored outside the production server.
- Restore is tested at least quarterly.
- The DPO knows the retention schedule.

---

## 7. Retention and disposal

TrafStat **does not delete** records automatically. Archiving keeps
records usable for history and audit. Actual disposal is a governance
action performed by the office under its Retention and Disposal Schedule.

Disposal should:

- Follow the office's DPO-approved retention period.
- Be recorded before any data is permanently removed.
- Prefer anonymization over deletion where historical aggregates need
  to survive.

**Never delete from `activity_logs`** — that breaks the audit chain.

---

## 8. Public website content

Two gates control what appears on the public website:

- `violation_categories.is_public = 1`
- `violation_categories.public_description` is non-empty

Publishing a category requires both, and requires the office's explicit
written approval of the public wording. Do not flip `is_public = 1`
without that approval on file.

`PUBLIC_SUPPRESSION_THRESHOLD` (in `config/config.php`) sets the minimum
group size for public statistics. Set with the DPO. Changing it is a
governance decision, not a technical one.

---

## 9. Routine maintenance

- Apply PHP and MySQL security updates promptly.
- Review `admin_sessions` and revoke stale sessions.
- Check `storage/exports/` is empty except transient files.
- Purge obsolete exports if the app has not done so (1-hour threshold
  is built in).
- Review security advisories for Bootstrap, Chart.js, Leaflet.

---

## 10. Escalation

When in doubt, escalate to the DPO. TrafStat's code does not decide
privacy questions. Use the office's incident-response process.