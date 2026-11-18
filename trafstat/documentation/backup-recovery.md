# TrafStat — Backup and Recovery

Owner: the office's System Administrator, on behalf of the DPO.
Reviewed with: hosting provider's operations contact.

---

## 1. What must be backed up

| Item | Where | How often |
|---|---|---|
| MySQL database `trafstat_db` | Managed MySQL | Daily full + hourly incremental (if available) |
| `storage/` contents | App server | Empty by design; not backed up |
| Application code | Git repo + deploy source | On every release |
| `config/config.php` | App server | Password manager (not a backup file) |
| TLS certificates | App server | Certbot renewal is the backup |

**Backups of the database include all personal information. Treat
them as containing the same sensitive data as the live database.**

---

## 2. Encryption

- Backups are encrypted **at rest** by the managed MySQL provider.
- If you export a database dump (`mysqldump`) for offsite storage,
  encrypt it **before** moving it:

    mysqldump --single-transaction --routines --triggers \
      --user=trafstat_backup -p trafstat_db \
      | gzip \
      | age -r <backup-public-key> \
      > trafstat_$(date +%Y%m%d_%H%M).sql.gz.age

- Store the **decryption private key separately** from the backup
  (e.g. in a hardware token held by the office, not in the same
  cloud account).
- Never store the decryption key alongside the encrypted file.
- Never store a plaintext dump on disk or email it.

---

## 3. Retention

Recommended defaults — the DPO sets the actual schedule:

| Backup type | Retention |
|---|---|
| Daily full | 30 days |
| Weekly full | 90 days |
| Monthly full | 1 year |
| Annual (post-audit) | Per office retention schedule |

Retention must be consistent with the office's Retention and Disposal
Schedule. Over-retention is a compliance problem too.

---

## 4. Restore procedure (tested)

Do this in **staging**, never on production unless recovering from a
real incident. Test at least quarterly.

### 4.1 Restore the database

    # Stop the app so nothing writes mid-restore
    sudo systemctl stop php8.2-fpm

    # If using managed MySQL: restore from the provider's console
    # If restoring from your own dump:
    age -d -i <private-key> trafstat_YYYYMMDD.sql.gz.age \
      | gunzip \
      | mysql --host=<private-ip> --user=trafstat_restore -p \
              --ssl-mode=REQUIRED trafstat_db

    # Restart the app
    sudo systemctl start php8.2-fpm

### 4.2 Verify

Run on the restored database:

    -- Row counts
    SELECT 'administrators', COUNT(*) FROM administrators;
    SELECT 'traffic_violators', COUNT(*) FROM traffic_violators;
    SELECT 'vehicles', COUNT(*) FROM vehicles;
    SELECT 'violation_categories', COUNT(*) FROM violation_categories;
    SELECT 'apprehension_records', COUNT(*) FROM apprehension_records;
    SELECT 'activity_logs', COUNT(*) FROM activity_logs;

Compare against the pre-incident snapshot you recorded. If any count is
lower, stop and investigate.

### 4.3 Chain verification

Log in as an administrator, open **Activity Logs**, click **Verify chain
integrity**. Expected: green. If the backup was truncated mid-chain, the
`truncation_warning` will appear — this is expected and acceptable for
a partial restore.

### 4.4 App-level smoke test

- Dashboard loads, counts match
- One violator edit and save works
- One apprehension preview renders
- Reports produce CSV

---

## 5. Recovery objectives

Default objectives to confirm with the DPO:

| Metric | Target |
|---|---|
| Recovery Time Objective (RTO) | 4 hours |
| Recovery Point Objective (RPO) | 1 hour |

If the RPO of 1 hour cannot be met by the provider's backup frequency,
the office must either accept the higher RPO or arrange incremental
binlogs. This is a governance decision.

---

## 6. Disaster recovery

If the entire app server is lost:

1. Provision a replacement server (deployment-guide §3–§8).
2. Restore the database from the most recent backup (this document §4).
3. Recreate `config/config.php` from the office's password manager.
4. Reissue the TLS certificate.
5. Provision administrator accounts. **Verify each person's identity
   is still current.**
6. Notify the DPO of the incident and the recovery.

Log all steps in the office's operations register. Recovery is an
incident for data-protection purposes if personal information was
exposed or availability was lost for a period — check with the DPO.

---

## 7. Backup verification

Backups that are never tested are not backups.

Minimum quarterly check:

- [ ] Restore latest daily backup into a staging database
- [ ] Verify row counts match
- [ ] Verify activity log chain
- [ ] Verify at least one admin can log in
- [ ] Delete the staging restore
- [ ] Record the result in the office's operations register

---

## 8. What to do when a backup is unusable

1. Do not silently fall back to an older backup.
2. Notify the DPO.
3. If the latest usable backup is older than the RPO, the incident
   is a potential availability breach — the DPO must decide whether
   NPC reporting is required.
4. Preserve any failed backup artifacts for forensics.