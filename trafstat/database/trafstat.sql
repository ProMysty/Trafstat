-- =====================================================================
-- TrafStat — Traffic Violation Records and Statistical Analytics System
-- Database schema  (MySQL 8.0+ / MariaDB 10.4+)
--
-- DEVELOPMENT SEED DATA ONLY. Do not import real personal data until the
-- responsible office has completed its Privacy Impact Assessment.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE DATABASE IF NOT EXISTS trafstat_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;



USE trafstat_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS admin_sessions;
DROP TABLE IF EXISTS apprehension_records;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS traffic_violators;
DROP TABLE IF EXISTS violation_categories;
DROP TABLE IF EXISTS administrators;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. administrators
--    Individually identifiable accounts. No shared credentials.
-- ---------------------------------------------------------------------
CREATE TABLE administrators (
    admin_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username              VARCHAR(50)  NOT NULL,
    full_name             VARCHAR(120) NOT NULL,
    email                 VARCHAR(150) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    account_status        ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until          DATETIME NULL DEFAULT NULL,
    mfa_enabled           TINYINT(1) NOT NULL DEFAULT 0,
    mfa_secret            VARCHAR(255) NULL DEFAULT NULL,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at         DATETIME NULL DEFAULT NULL,
    password_changed_at   DATETIME NULL DEFAULT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admin_username (username),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. traffic_violators
-- ---------------------------------------------------------------------
CREATE TABLE traffic_violators (
    violator_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    violator_code  VARCHAR(20)  NOT NULL,           -- e.g. VIO-2026-00001
    first_name     VARCHAR(60)  NOT NULL,
    middle_name    VARCHAR(60)  NULL DEFAULT NULL,
    last_name      VARCHAR(60)  NOT NULL,
    suffix         VARCHAR(10)  NULL DEFAULT NULL,
    address        VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20)  NULL DEFAULT NULL,  -- only if authorised
    record_status  ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by     INT UNSIGNED NULL DEFAULT NULL,
    updated_by     INT UNSIGNED NULL DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (violator_id),
    UNIQUE KEY uq_violator_code (violator_code),
    KEY idx_violator_name (last_name, first_name),
    KEY idx_violator_status (record_status),
    CONSTRAINT fk_violator_created_by FOREIGN KEY (created_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_violator_updated_by FOREIGN KEY (updated_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. vehicles
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    vehicle_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    violator_id       INT UNSIGNED NULL DEFAULT NULL,
    plate_number      VARCHAR(20)  NOT NULL,
    vehicle_type      VARCHAR(40)  NULL DEFAULT NULL,
    vehicle_make      VARCHAR(50)  NULL DEFAULT NULL,
    vehicle_model     VARCHAR(50)  NULL DEFAULT NULL,
    vehicle_color     VARCHAR(30)  NULL DEFAULT NULL,
    registration_info VARCHAR(150) NULL DEFAULT NULL,
    record_status     ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by        INT UNSIGNED NULL DEFAULT NULL,
    updated_by        INT UNSIGNED NULL DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (vehicle_id),
    UNIQUE KEY uq_vehicle_plate (plate_number),
    KEY idx_vehicle_violator (violator_id),
    KEY idx_vehicle_status (record_status),
    CONSTRAINT fk_vehicle_violator FOREIGN KEY (violator_id)
        REFERENCES traffic_violators (violator_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_created_by FOREIGN KEY (created_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_updated_by FOREIGN KEY (updated_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. violation_categories
--    is_public / public_description gate what the public site may show.
-- ---------------------------------------------------------------------
CREATE TABLE violation_categories (
    category_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    violation_name     VARCHAR(150) NOT NULL,
    description        TEXT NULL DEFAULT NULL,
    public_description TEXT NULL DEFAULT NULL,
    is_public          TINYINT(1) NOT NULL DEFAULT 0,
    status             ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by         INT UNSIGNED NULL DEFAULT NULL,
    updated_by         INT UNSIGNED NULL DEFAULT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_violation_name (violation_name),
    KEY idx_category_status (status),
    CONSTRAINT fk_category_created_by FOREIGN KEY (created_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_category_updated_by FOREIGN KEY (updated_by)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. apprehension_records
--    A recorded apprehension is NOT a legal finding of guilt.
-- ---------------------------------------------------------------------
CREATE TABLE apprehension_records (
    apprehension_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_no      VARCHAR(25)  NOT NULL,        -- e.g. APP-2026-000123
    violator_id       INT UNSIGNED NOT NULL,
    vehicle_id        INT UNSIGNED NULL DEFAULT NULL,
    category_id       INT UNSIGNED NOT NULL,
    apprehension_date DATE NOT NULL,
    apprehension_time TIME NULL DEFAULT NULL,
    location_text     VARCHAR(255) NOT NULL,
    latitude          DECIMAL(10,7) NULL DEFAULT NULL,
    longitude         DECIMAL(10,7) NULL DEFAULT NULL,
    remarks           TEXT NULL DEFAULT NULL,
    recorded_by       INT UNSIGNED NOT NULL,
    record_status     ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (apprehension_id),
    UNIQUE KEY uq_apprehension_ref (reference_no),
    KEY idx_app_date (apprehension_date),
    KEY idx_app_violator (violator_id),
    KEY idx_app_vehicle (vehicle_id),
    KEY idx_app_category (category_id),
    KEY idx_app_location (location_text),
    KEY idx_app_geo (latitude, longitude),
    KEY idx_app_status (record_status),
    CONSTRAINT fk_app_violator FOREIGN KEY (violator_id)
        REFERENCES traffic_violators (violator_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_vehicle FOREIGN KEY (vehicle_id)
        REFERENCES vehicles (vehicle_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_category FOREIGN KEY (category_id)
        REFERENCES violation_categories (category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_recorded_by FOREIGN KEY (recorded_by)
        REFERENCES administrators (admin_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. activity_logs  (append-only, hash-chained)
--    Never store passwords, session secrets or unnecessary PII here.
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    log_id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id           INT UNSIGNED NULL DEFAULT NULL,
    event_type         VARCHAR(50)  NOT NULL,
    action_description VARCHAR(500) NOT NULL,
    related_table      VARCHAR(50)  NULL DEFAULT NULL,
    related_record_id  BIGINT UNSIGNED NULL DEFAULT NULL,
    details            JSON NULL DEFAULT NULL,
    ip_address         VARCHAR(45)  NULL DEFAULT NULL,
    user_agent         VARCHAR(255) NULL DEFAULT NULL,
    previous_hash      CHAR(64) NOT NULL,
    entry_hash         CHAR(64) NOT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_log_admin (admin_id),
    KEY idx_log_event (event_type),
    KEY idx_log_created (created_at),
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id)
        REFERENCES administrators (admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. login_attempts  (rate limiting by username AND IP)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
    attempt_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50) NOT NULL,
    ip_address     VARCHAR(45) NOT NULL,
    was_successful TINYINT(1)  NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY idx_attempt_username_time (username, created_at),
    KEY idx_attempt_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. admin_sessions  (server-side session registry + revocation)
-- ---------------------------------------------------------------------
CREATE TABLE admin_sessions (
    session_hash  CHAR(64) NOT NULL,          -- sha256 of PHP session id
    admin_id      INT UNSIGNED NOT NULL,
    ip_address    VARCHAR(45) NOT NULL,
    user_agent    VARCHAR(255) NULL DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (session_hash),
    KEY idx_session_admin (admin_id),
    CONSTRAINT fk_session_admin FOREIGN KEY (admin_id)
        REFERENCES administrators (admin_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- DEVELOPMENT SEED DATA
-- Categories are EXAMPLES ONLY and must be approved/edited by the office.
-- is_public = 0 until the office approves the public wording.
-- ---------------------------------------------------------------------
INSERT INTO violation_categories (violation_name, description, public_description, is_public, status) VALUES
('Illegal Parking',            'Parking in a prohibited or obstructing location.', NULL, 0, 'active'),
('No Helmet',                  'Riding a motorcycle without the required protective helmet.', NULL, 0, 'active'),
('Disregarding Traffic Signs', 'Failure to obey a posted regulatory traffic sign or signal.', NULL, 0, 'active'),
('Overloading',                'Carrying passengers or cargo beyond the authorised capacity.', NULL, 0, 'active'),
('Driving Without Required Documents', 'Operating a vehicle without the required documents in hand.', NULL, 0, 'active');

-- ---------------------------------------------------------------------
-- PRODUCTION NOTE — do not run as root in production.
-- Create a least-privilege application account instead:
--
--   CREATE USER 'trafstat_app'@'localhost' IDENTIFIED BY '<strong-password>';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON trafstat_db.* TO 'trafstat_app'@'localhost';
--   FLUSH PRIVILEGES;
--
-- The application never needs DROP, ALTER or GRANT privileges at runtime.
-- ---------------------------------------------------------------------

-- 1. Create the application user
CREATE USER 'trafstat_user'@'localhost'
  IDENTIFIED BY 'goldirec098';

-- 2. Grant only the privileges TrafStat actually needs
GRANT SELECT, INSERT, UPDATE, DELETE
  ON trafstat_db.*
  TO 'trafstat_user'@'localhost';

-- 3. Apply
FLUSH PRIVILEGES;