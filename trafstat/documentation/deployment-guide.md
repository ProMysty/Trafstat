# TrafStat — Production Deployment Guide

Audience: the office's IT team, a hosting provider engaged by the office,
and the DPO.

**This document does not approve deployment.** It describes how to deploy
a system that has already been approved for deployment. Nothing here
substitutes for:

- Office approval of the hosting environment
- DPO completion of a Privacy Impact Assessment
- Signed contracts (including any Personal Information Processor
  agreement with the hosting provider)
- Signed acceptance of the Security Testing Report and UAT Report

---

## 1. Reference architecture

    ┌───────────────────────────┐        ┌────────────────────────────┐
    │  Application server (VPS) │        │  Managed MySQL (private)   │
    │  Ubuntu 22.04/24.04 LTS   │        │  Encrypted at rest         │
    │  Nginx + PHP-FPM 8.2+     │◄──────►│  TLS-only connection       │
    │  TrafStat web root        │        │  Automated backups         │
    │  HTTPS (TLS 1.2+)         │        │  No public MySQL access    │
    │  Firewall (22, 443 only)  │        └────────────────────────────┘
    └───────────────────────────┘

Optional: a separate object-storage bucket in the same region for
encrypted backups.

---

## 2. Before you begin — required approvals

Do not deploy until you have **written** confirmation of:

- [ ] Hosting provider approved by the office
- [ ] Data location (country / region) approved by the DPO
- [ ] Privacy Impact Assessment completed
- [ ] Retention schedule approved by the DPO
- [ ] Administrator list approved by the office
- [ ] Confidentiality agreements signed for all administrators
- [ ] Import procedure for any existing records approved by the DPO
- [ ] Backup and recovery plan reviewed

Store these approvals in the office's records. Deployment proceeds only
after they exist.

---

## 3. Provision the server

Reference: Ubuntu 22.04/24.04 on an approved VPS.

    # Add a non-root sudo user, disable password SSH, enable key auth
    adduser deploy
    usermod -aG sudo deploy
    rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

    # From a local terminal:
    ssh-copy-id deploy@<server-ip>
    ssh deploy@<server-ip>   # verify key login works

    sudo sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
    sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
    sudo systemctl restart ssh

Firewall:

    sudo ufw default deny incoming
    sudo ufw allow OpenSSH
    sudo ufw allow 'Nginx Full'
    sudo ufw enable
    sudo ufw status

**Do not** open MySQL to the internet.

---

## 4. Install runtime

    sudo apt update
    sudo apt install -y nginx php8.2-fpm php8.2-mysql php8.2-mbstring \
                        php8.2-xml php8.2-curl php8.2-zip unzip

Verify PHP-FPM:

    php -v
    sudo systemctl status php8.2-fpm

---

## 5. Deploy the application

    sudo mkdir -p /var/www/trafstat
    sudo chown deploy:www-data /var/www/trafstat

From your local machine (after review approval):

    rsync -av --exclude 'storage/' --exclude 'config/config.php' \
      --exclude '.git/' --exclude 'documentation/' \
      ./trafstat/ deploy@<server-ip>:/var/www/trafstat/

On the server:

    cd /var/www/trafstat
    mkdir -p storage/exports
    chown -R deploy:www-data .
    find . -type d -exec chmod 750 {} \;
    find . -type f -exec chmod 640 {} \;
    chmod 750 storage storage/exports

Create the production config **on the server** — never copy from dev:

    cp config/config.example.php config/config.php
    chmod 640 config/config.php
    chown deploy:www-data config/config.php

Edit `config/config.php`:

- `DB_HOST` = the **private** MySQL address
- `DB_USER` = dedicated app user (SELECT, INSERT, UPDATE, DELETE only)
- `DB_PASS` = generated strong password, stored in the office's
  password manager
- `APP_ENV` = `production`
- `APP_URL` = `https://<production-domain>`
- `PUBLIC_SUPPRESSION_THRESHOLD` = value approved by the DPO

**Never** commit `config/config.php` to source control.

---

## 6. Nginx site

`/etc/nginx/sites-available/trafstat`:

    server {
        listen 80;
        server_name <production-domain>;
        return 301 https://$host$request_uri;
    }

    server {
        listen 443 ssl http2;
        server_name <production-domain>;
        root /var/www/trafstat;
        index index.php;

        ssl_certificate     /etc/letsencrypt/live/<production-domain>/fullchain.pem;
        ssl_certificate_key /etc/letsencrypt/live/<production-domain>/privkey.pem;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!MD5;
        ssl_prefer_server_ciphers on;

        add_header X-Content-Type-Options nosniff always;
        add_header X-Frame-Options DENY always;
        add_header Referrer-Policy strict-origin-when-cross-origin always;

        # Block direct access to protected directories
        location ~ ^/(config|includes|database|documentation|storage)/ {
            deny all;
            return 403;
        }
        location ~ ^/(tools)/ {
            deny all;
            return 403;
        }

        # Block dotfiles and non-PHP config files
        location ~ /\.(?!well-known) { deny all; }
        location ~ \.(sql|md|log|ini|env)$ { deny all; }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        }
    }

Enable:

    sudo ln -s /etc/nginx/sites-available/trafstat /etc/nginx/sites-enabled/
    sudo nginx -t
    sudo systemctl reload nginx

HTTPS:

    sudo apt install -y certbot python3-certbot-nginx
    sudo certbot --nginx -d <production-domain>
    sudo systemctl enable certbot.timer

Verify:

    curl -s -o /dev/null -w "%{http_code}\n" https://<production-domain>/
    curl -s -o /dev/null -w "%{http_code}\n" \
      https://<production-domain>/config/config.php
    curl -s -o /dev/null -w "%{http_code}\n" \
      https://<production-domain>/storage/exports/

Expected: `200`, `403`, `403`.

---

## 7. Database

- Create a managed MySQL instance in the same region as the app server.
- Enable TLS-only connections. Configure `PDO::MYSQL_ATTR_SSL_CA` in
  `config/database.php` with the provider's CA bundle.
- Enable encryption at rest.
- Restrict network access to the app server's private IP only.
- Enable automated daily backups + point-in-time recovery if available.
- Import `database/trafstat.sql` once into a fresh database.
- Create the app user with only `SELECT, INSERT, UPDATE, DELETE`.
- Do **not** create a `DROP` or `ALTER` user for runtime.

Test connectivity from the app server:

    mysql --host=<private-ip> --user=trafstat_user -p \
          --ssl-mode=REQUIRED trafstat_db -e "SELECT 1;"

If `--ssl-mode=REQUIRED` fails, the TLS setup is wrong. Do not proceed.

---

## 8. Administrator provisioning in production

    cd /var/www/trafstat
    sudo -u www-data php tools/create_admin.php <username> \
      "<Full Name>" <email>

Repeat once per approved administrator. Delete or chmod-lock the
`tools/` directory after all accounts are provisioned (the Nginx rule
above already blocks HTTP access, but defense in depth).

---

## 9. Scheduled maintenance

Add a daily cron on the app server:

    sudo crontab -u www-data -e

    # Purge orphaned exports daily at 03:00
    0 3 * * * find /var/www/trafstat/storage/exports -type f -mtime +1 -delete

Monitor:

- `php8.2-fpm` error log
- Nginx access/error log
- `login_attempts` table growth (weekly)
- Certificate expiry (Certbot renews automatically; alert if renewal fails)

---

## 10. First-day checks

After deployment:

- [ ] Admin login works for one approved account
- [ ] Change-password flow forces correctly
- [ ] All 8 tables reachable from `admin/dashboard.php`
- [ ] Dashboard charts render
- [ ] Hotspot map loads tiles
- [ ] Reports print/CSV export work
- [ ] Public site loads (index, statistics, violations, about)
- [ ] Public site has no PII in source (grep for a known test name)
- [ ] Activity log verify returns green
- [ ] HTTPS certificate valid, redirect from HTTP works
- [ ] `config/config.php`, `/storage/`, `/tools/` return 403

Any failure → do not hand over to users. Fix, then repeat.

---

## 11. Rollback

Keep the previous release on the server as `trafstat-previous/`. On
failure:

    sudo systemctl stop php8.2-fpm
    sudo mv /var/www/trafstat /var/www/trafstat-failed
    sudo mv /var/www/trafstat-previous /var/www/trafstat
    sudo systemctl start php8.2-fpm

Then restore the matching database snapshot. **Test the rollback path
at least once in staging.**