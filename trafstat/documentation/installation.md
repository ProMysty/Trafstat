# TrafStat — Installation Guide (Development)

Target: a developer setting up TrafStat on a local Linux machine with
XAMPP, for development or demonstration purposes only.

**Do not use this guide for production deployment.** See `deployment-guide.md`.

---

## 1. Requirements

- XAMPP for Linux (current release) — includes Apache, MySQL/MariaDB, PHP 8.1+
- A modern browser (Chrome, Firefox, Edge)
- VS Code (recommended) or any text editor
- Terminal access

Verify what you have:

    /opt/lampp/bin/php -v
    /opt/lampp/bin/mysql --version
    sudo /opt/lampp/lampp status

All three should report a running, supported version.

---

## 2. Project placement

    sudo cp -r trafstat /opt/lampp/htdocs/
    sudo chown -R $USER:$USER /opt/lampp/htdocs/trafstat
    cd /opt/lampp/htdocs/trafstat

Confirm the web root responds:

    curl -s -o /dev/null -w "%{http_code}\n" http://localhost/trafstat/

`403` or `200` is fine at this stage.

---

## 3. Create the database

Open phpMyAdmin (http://localhost/phpmyadmin) or the MySQL shell:

    sudo /opt/lampp/bin/mysql -u root

Run the schema:

    SOURCE /opt/lampp/htdocs/trafstat/database/trafstat.sql;
    EXIT;

Verify the tables exist:

    sudo /opt/lampp/bin/mysql -u root -e "USE trafstat_db; SHOW TABLES;"

You should see 8 tables:

    activity_logs
    admin_sessions
    administrators
    apprehension_records
    login_attempts
    traffic_violators
    vehicles
    violation_categories

---

## 4. Create the least-privilege MySQL user

    sudo /opt/lampp/bin/mysql -u root

Then:

    CREATE USER 'trafstat_user'@'localhost'
      IDENTIFIED BY '<STRONG-PASSWORD>';
    GRANT SELECT, INSERT, UPDATE, DELETE
      ON trafstat_db.*
      TO 'trafstat_user'@'localhost';
    FLUSH PRIVILEGES;
    EXIT;

Test that the user can actually connect:

    mysql -u trafstat_user -p trafstat_db -e "SELECT 'OK' AS status;"

Should print `OK`. If it says "Access denied", the password or the host
(`localhost` vs `127.0.0.1`) is wrong — fix it before continuing.

---

## 5. Configure the application

    cp config/config.example.php config/config.php
    chmod 640 config/config.php

Edit `config/config.php` and set at least:

    define('DB_USER', 'trafstat_user');
    define('DB_PASS', '<same-password-as-step-4>');

For local development, `APP_ENV = 'development'` (default) prints full
PDO errors to help debugging. Change to `'production'` **before any
real data is entered.**

Confirm the config file is unreachable via the browser:

    curl -s -o /dev/null -w "%{http_code}\n" \
      http://localhost/trafstat/config/config.php

Expected: `403`.

---

## 6. Create the first administrator

The provisioning script is CLI-only and refuses HTTP requests.

    /opt/lampp/bin/php tools/create_admin.php superadmin \
      "Full Name" admin@example.gov.ph

The script prints a **temporary password** to the terminal. Copy it now —
it is not stored anywhere in readable form. The account is flagged
`must_change_password = 1` and will be forced to change it on first login.

Confirm the row exists:

    sudo /opt/lampp/bin/mysql -u root trafstat_db \
      -e "SELECT admin_id, username, account_status FROM administrators;"

---

## 7. First login

Open http://localhost/trafstat/admin/login.php

Log in with the username and the temporary password. You will be redirected
to `/admin/change_password.php` before you can reach any other page. Set a
strong permanent password and save it in a password manager.

---

## 8. Directory permissions (Linux)

The app writes only under `storage/exports/`. Everything else is read-only
at runtime:

    find . -type f -not -path "./storage/*" -exec chmod 640 {} \;
    find . -type d -not -path "./storage*" -exec chmod 750 {} \;
    chmod 640 config/config.php
    chmod 750 storage storage/exports

Confirm:

    curl -s -o /dev/null -w "%{http_code}\n" \
      http://localhost/trafstat/storage/exports/

Expected: `403`.

---

## 9. Common setup problems

| Symptom | Cause | Fix |
|---|---|---|
| `[1045] Access denied for user …` | Wrong password or user doesn't exist | Re-run step 4; check `DB_USER`/`DB_PASS` match exactly |
| `[1044] Access denied … to database` | User exists, grants missing | Re-run `GRANT … FLUSH PRIVILEGES` |
| `[2002] Connection refused` | MySQL not running | `/opt/lampp/lampp start` |
| `Unknown MySQL server host 'localhost'` on some setups | `localhost` resolves to a socket MySQL isn't listening on | Change `DB_HOST` to `127.0.0.1` and grant the user for that host |
| `Access denied … 'trafstat_app'@'localhost'` | Username mismatch | You probably edited `config.example.php` — confirm `config/config.php` is the one loaded |
| "Violator creation fails silently" | You hit the `:admin, :admin` placeholder bug | Already fixed — see `save_violator.php` and `save_vehicle.php` use `:created_by` / `:updated_by` |
| "No map displayed" | No apprehension has coordinates | Add lat/lng via `admin/apprehensions.php` (edit any record); or check `PUBLIC_SUPPRESSION_THRESHOLD` isn't accidentally 100+ |

---

## 10. Verify the install

Complete the checklist in `documentation/security-testing-report.md`
(Section A). If all A-items pass, the install is sound.

---

## 11. Do not proceed to production

This guide produced a **development install**. Before handling any real
records:

1. Read `deployment-guide.md` in full.
2. Get the office's written approval for the deployment architecture.
3. Have the DPO complete the Privacy Impact Assessment.
4. Transfer only through an approved import process.

Nothing in the code prevents production deployment, but nothing in the
code substitutes for the office's governance steps.