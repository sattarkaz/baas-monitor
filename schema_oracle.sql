-- =============================================================================
-- BaaS Partner Monitoring System
-- Oracle SQL Schema + Mock Data
-- Database: Oracle 19c+
-- Schema owner: BAAS_MONITOR
-- =============================================================================

-- =============================================================================
-- SECTION 1: SEQUENCES (auto-increment for Oracle)
-- =============================================================================

CREATE SEQUENCE seq_partner_id       START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_package_id       START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_snapshot_id      START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_kpi_id           START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_user_id          START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_role_id          START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_alert_id         START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_alert_log_id     START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_sync_id          START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_txn_cache_id     START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

-- =============================================================================
-- SECTION 2: MONITORING SYSTEM TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- ROLES — access levels and permissions
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    role_id          NUMBER         DEFAULT seq_role_id.NEXTVAL PRIMARY KEY,
    role_name        VARCHAR2(50)   NOT NULL,
    permissions      VARCHAR2(4000),          -- JSON blob: {"dashboard": true, "admin": false, ...}
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT uq_role_name UNIQUE (role_name)
);

-- -----------------------------------------------------------------------------
-- USERS — system users
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    user_id          NUMBER         DEFAULT seq_user_id.NEXTVAL PRIMARY KEY,
    full_name        VARCHAR2(150)  NOT NULL,
    email            VARCHAR2(200)  NOT NULL,
    password_hash    VARCHAR2(256)  NOT NULL,
    role_id          NUMBER         NOT NULL,
    status           VARCHAR2(20)   DEFAULT 'active' NOT NULL,
    last_login_at    TIMESTAMP,
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id),
    CONSTRAINT uq_user_email UNIQUE (email),
    CONSTRAINT ck_user_status CHECK (status IN ('active', 'inactive', 'locked'))
);

-- -----------------------------------------------------------------------------
-- PARTNERS — BaaS partner directory
-- -----------------------------------------------------------------------------
CREATE TABLE partners (
    partner_id       NUMBER         DEFAULT seq_partner_id.NEXTVAL PRIMARY KEY,
    partner_name     VARCHAR2(200)  NOT NULL,
    legal_form       VARCHAR2(50),
    status           VARCHAR2(20)   DEFAULT 'active' NOT NULL,
    contract_date    DATE           NOT NULL,
    contact_email    VARCHAR2(200),
    account_manager  VARCHAR2(150),
    created_by       NUMBER,
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT ck_partner_status CHECK (status IN ('active', 'inactive', 'suspended')),
    CONSTRAINT fk_partners_creator FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- -----------------------------------------------------------------------------
-- CARD_PACKAGES — prepaid card packages allocated per partner
-- -----------------------------------------------------------------------------
CREATE TABLE card_packages (
    package_id       NUMBER         DEFAULT seq_package_id.NEXTVAL PRIMARY KEY,
    partner_id       NUMBER         NOT NULL,
    package_size     NUMBER(10)     NOT NULL,
    start_date       DATE           NOT NULL,
    end_date         DATE           NOT NULL,
    status           VARCHAR2(20)   DEFAULT 'active' NOT NULL,
    notes            VARCHAR2(1000),
    created_by       NUMBER,
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT fk_packages_partner FOREIGN KEY (partner_id) REFERENCES partners(partner_id),
    CONSTRAINT fk_packages_creator FOREIGN KEY (created_by) REFERENCES users(user_id),
    CONSTRAINT ck_package_status   CHECK (status IN ('active', 'exhausted', 'closed')),
    CONSTRAINT ck_package_dates    CHECK (end_date > start_date),
    CONSTRAINT ck_package_size     CHECK (package_size > 0)
);

-- -----------------------------------------------------------------------------
-- PACKAGE_USAGE_SNAPSHOT — daily snapshot of card issuance per package
-- Populated by scheduled DWH sync job
-- -----------------------------------------------------------------------------
CREATE TABLE package_usage_snapshot (
    snapshot_id      NUMBER         DEFAULT seq_snapshot_id.NEXTVAL PRIMARY KEY,
    snapshot_date    DATE           NOT NULL,
    package_id       NUMBER         NOT NULL,
    partner_id       NUMBER         NOT NULL,
    issued_cards     NUMBER(10)     DEFAULT 0 NOT NULL,
    remaining_cards  NUMBER(10)     DEFAULT 0 NOT NULL,
    usage_percent    NUMBER(5,2)    DEFAULT 0 NOT NULL,
    source_sync_id   NUMBER,
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT fk_snapshot_package  FOREIGN KEY (package_id)  REFERENCES card_packages(package_id),
    CONSTRAINT fk_snapshot_partner  FOREIGN KEY (partner_id)  REFERENCES partners(partner_id),
    CONSTRAINT uq_snapshot_pkg_date UNIQUE (snapshot_date, package_id),
    CONSTRAINT ck_usage_percent     CHECK (usage_percent BETWEEN 0 AND 100),
    CONSTRAINT ck_remaining_cards   CHECK (remaining_cards >= 0)
);

-- -----------------------------------------------------------------------------
-- TRANSACTION_DAILY_AGG_CACHE — cached daily aggregates from DWH
-- Source: dwh_transaction_daily_agg
-- -----------------------------------------------------------------------------
CREATE TABLE transaction_daily_agg_cache (
    cache_id               NUMBER           DEFAULT seq_txn_cache_id.NEXTVAL PRIMARY KEY,
    agg_date               DATE             NOT NULL,
    partner_id             NUMBER           NOT NULL,
    currency               VARCHAR2(3)      DEFAULT 'RUB' NOT NULL,
    transaction_count      NUMBER(15)       DEFAULT 0 NOT NULL,
    transaction_volume     NUMBER(20,2)     DEFAULT 0 NOT NULL,
    avg_transaction_amount NUMBER(18,2)     DEFAULT 0,
    source_sync_id         NUMBER,
    created_at             TIMESTAMP        DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT fk_txncache_partner FOREIGN KEY (partner_id) REFERENCES partners(partner_id),
    CONSTRAINT uq_txncache_date_prt UNIQUE (agg_date, partner_id, currency)
);

-- -----------------------------------------------------------------------------
-- PARTNER_KPI_SETTINGS — user-defined KPI targets per partner
-- -----------------------------------------------------------------------------
CREATE TABLE partner_kpi_settings (
    kpi_id           NUMBER         DEFAULT seq_kpi_id.NEXTVAL PRIMARY KEY,
    partner_id       NUMBER         NOT NULL,
    metric_code      VARCHAR2(50)   NOT NULL,  -- e.g. 'USAGE_PCT', 'TXN_VOLUME_MONTHLY'
    target_value     NUMBER(20,4)   NOT NULL,
    period_type      VARCHAR2(20)   DEFAULT 'MONTHLY',
    valid_from       DATE           NOT NULL,
    valid_to         DATE,
    created_by       NUMBER,
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT fk_kpi_partner  FOREIGN KEY (partner_id)  REFERENCES partners(partner_id),
    CONSTRAINT fk_kpi_creator  FOREIGN KEY (created_by)  REFERENCES users(user_id),
    CONSTRAINT ck_kpi_period   CHECK (period_type IN ('DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'))
);

-- -----------------------------------------------------------------------------
-- ALERTS_CONFIG — threshold-based alert rules
-- -----------------------------------------------------------------------------
CREATE TABLE alerts_config (
    alert_id           NUMBER         DEFAULT seq_alert_id.NEXTVAL PRIMARY KEY,
    alert_name         VARCHAR2(200)  NOT NULL,
    metric_code        VARCHAR2(50)   NOT NULL,
    threshold_value    NUMBER(20,4)   NOT NULL,
    condition_op       VARCHAR2(10)   NOT NULL,  -- 'GT', 'LT', 'GTE', 'LTE', 'EQ'
    recipient_group    VARCHAR2(200),
    is_active          NUMBER(1)      DEFAULT 1 NOT NULL,
    created_by         NUMBER,
    created_at         TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT ck_alert_condition CHECK (condition_op IN ('GT', 'LT', 'GTE', 'LTE', 'EQ')),
    CONSTRAINT ck_alert_active    CHECK (is_active IN (0, 1))
);

-- -----------------------------------------------------------------------------
-- ALERTS_LOG — history of fired alerts
-- -----------------------------------------------------------------------------
CREATE TABLE alerts_log (
    alert_log_id     NUMBER         DEFAULT seq_alert_log_id.NEXTVAL PRIMARY KEY,
    alert_id         NUMBER         NOT NULL,
    partner_id       NUMBER,
    package_id       NUMBER,
    triggered_at     TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    actual_value     NUMBER(20,4)   NOT NULL,
    threshold_value  NUMBER(20,4)   NOT NULL,
    is_acknowledged  NUMBER(1)      DEFAULT 0 NOT NULL,
    ack_by           NUMBER,
    ack_at           TIMESTAMP,
    CONSTRAINT fk_alertlog_alert   FOREIGN KEY (alert_id)   REFERENCES alerts_config(alert_id),
    CONSTRAINT fk_alertlog_partner FOREIGN KEY (partner_id) REFERENCES partners(partner_id),
    CONSTRAINT fk_alertlog_ackby   FOREIGN KEY (ack_by)     REFERENCES users(user_id)
);

-- -----------------------------------------------------------------------------
-- DWH_SYNC_LOG — audit log of DWH sync jobs
-- -----------------------------------------------------------------------------
CREATE TABLE dwh_sync_log (
    sync_id          NUMBER         DEFAULT seq_sync_id.NEXTVAL PRIMARY KEY,
    source_name      VARCHAR2(100)  NOT NULL,  -- e.g. 'dwh_card_daily_agg'
    started_at       TIMESTAMP      NOT NULL,
    finished_at      TIMESTAMP,
    status           VARCHAR2(20)   DEFAULT 'RUNNING' NOT NULL,
    rows_loaded      NUMBER(15)     DEFAULT 0,
    error_message    VARCHAR2(4000),
    created_at       TIMESTAMP      DEFAULT SYSTIMESTAMP NOT NULL,
    CONSTRAINT ck_sync_status CHECK (status IN ('RUNNING', 'SUCCESS', 'FAILED', 'PARTIAL'))
);

-- =============================================================================
-- SECTION 3: INDEXES
-- =============================================================================

CREATE INDEX idx_packages_partner     ON card_packages(partner_id);
CREATE INDEX idx_snapshot_date        ON package_usage_snapshot(snapshot_date);
CREATE INDEX idx_snapshot_partner     ON package_usage_snapshot(partner_id);
CREATE INDEX idx_snapshot_package     ON package_usage_snapshot(package_id);
CREATE INDEX idx_txncache_date        ON transaction_daily_agg_cache(agg_date);
CREATE INDEX idx_txncache_partner     ON transaction_daily_agg_cache(partner_id);
CREATE INDEX idx_alertlog_triggered   ON alerts_log(triggered_at);
CREATE INDEX idx_alertlog_partner     ON alerts_log(partner_id);
CREATE INDEX idx_kpi_partner          ON partner_kpi_settings(partner_id);

-- =============================================================================
-- SECTION 4: DWH OBJECTS (External Views / DB Links)
-- These represent objects in the Data Warehouse that the monitoring system
-- reads via a DB Link (BAAS_DWH_LINK). In demo mode they are local tables.
-- =============================================================================

-- Simulated DWH table: daily card issuance aggregates
CREATE TABLE dwh_card_daily_agg (
    agg_date         DATE           NOT NULL,
    partner_id       NUMBER         NOT NULL,
    package_id       NUMBER         NOT NULL,
    cards_issued     NUMBER(10)     DEFAULT 0 NOT NULL,
    cards_active     NUMBER(10)     DEFAULT 0 NOT NULL,
    cards_blocked    NUMBER(10)     DEFAULT 0 NOT NULL,
    PRIMARY KEY (agg_date, partner_id, package_id)
);

-- Simulated DWH table: daily transaction aggregates
CREATE TABLE dwh_transaction_daily_agg (
    agg_date               DATE           NOT NULL,
    partner_id             NUMBER         NOT NULL,
    currency               VARCHAR2(3)    NOT NULL,
    transaction_count      NUMBER(15)     DEFAULT 0 NOT NULL,
    transaction_volume     NUMBER(20,2)   DEFAULT 0 NOT NULL,
    avg_transaction_amount NUMBER(18,2),
    PRIMARY KEY (agg_date, partner_id, currency)
);

-- Simulated DWH table: MCC-level transaction aggregates
CREATE TABLE dwh_transaction_mcc_agg (
    agg_date               DATE           NOT NULL,
    partner_id             NUMBER         NOT NULL,
    mcc                    VARCHAR2(4)    NOT NULL,
    mcc_description        VARCHAR2(200),
    transaction_count      NUMBER(15)     DEFAULT 0,
    transaction_volume     NUMBER(20,2)   DEFAULT 0,
    PRIMARY KEY (agg_date, partner_id, mcc)
);

-- Simulated DWH table: country-level transaction aggregates
CREATE TABLE dwh_transaction_country_agg (
    agg_date               DATE           NOT NULL,
    partner_id             NUMBER         NOT NULL,
    merchant_country       VARCHAR2(2)    NOT NULL,
    transaction_count      NUMBER(15)     DEFAULT 0,
    transaction_volume     NUMBER(20,2)   DEFAULT 0,
    PRIMARY KEY (agg_date, partner_id, merchant_country)
);

-- =============================================================================
-- SECTION 5: VIEWS (Business Layer)
-- =============================================================================

-- Active package usage — current state
CREATE OR REPLACE VIEW v_active_package_usage AS
SELECT
    p.partner_id,
    p.partner_name,
    p.status                                          AS partner_status,
    cp.package_id,
    cp.package_size,
    cp.start_date,
    cp.end_date,
    cp.status                                         AS package_status,
    s.issued_cards,
    s.remaining_cards,
    ROUND(s.issued_cards / NULLIF(cp.package_size, 0) * 100, 2) AS usage_percent,
    s.snapshot_date                                   AS last_updated
FROM partners p
JOIN card_packages cp  ON cp.partner_id = p.partner_id
JOIN package_usage_snapshot s ON s.package_id = cp.package_id
    AND s.snapshot_date = (
        SELECT MAX(s2.snapshot_date)
        FROM package_usage_snapshot s2
        WHERE s2.package_id = cp.package_id
    );

-- Partner transaction summary (last 30 days, from cache)
CREATE OR REPLACE VIEW v_partner_txn_summary_30d AS
SELECT
    p.partner_id,
    p.partner_name,
    SUM(t.transaction_count)  AS txn_count_30d,
    SUM(t.transaction_volume) AS txn_volume_30d,
    AVG(t.avg_transaction_amount) AS avg_ticket_30d,
    MAX(t.agg_date)           AS latest_agg_date
FROM partners p
LEFT JOIN transaction_daily_agg_cache t
    ON t.partner_id = p.partner_id
    AND t.agg_date >= TRUNC(SYSDATE) - 30
GROUP BY p.partner_id, p.partner_name;

-- Dashboard KPI summary
CREATE OR REPLACE VIEW v_dashboard_kpis AS
SELECT
    (SELECT COUNT(*) FROM partners WHERE status = 'active')          AS active_partners,
    (SELECT COUNT(*) FROM card_packages WHERE status = 'active')     AS active_packages,
    (SELECT SUM(issued_cards) FROM package_usage_snapshot s
     WHERE s.snapshot_date = (SELECT MAX(s2.snapshot_date) FROM package_usage_snapshot s2
                               WHERE s2.package_id = s.package_id))  AS total_issued_cards,
    (SELECT SUM(remaining_cards) FROM package_usage_snapshot s
     WHERE s.snapshot_date = (SELECT MAX(s2.snapshot_date) FROM package_usage_snapshot s2
                               WHERE s2.package_id = s.package_id))  AS total_remaining_cards,
    (SELECT SUM(transaction_volume)
     FROM transaction_daily_agg_cache
     WHERE agg_date >= TRUNC(SYSDATE) - 30)                          AS txn_volume_30d,
    (SELECT SUM(transaction_count)
     FROM transaction_daily_agg_cache
     WHERE agg_date >= TRUNC(SYSDATE) - 30)                          AS txn_count_30d
FROM DUAL;

-- =============================================================================
-- SECTION 6: MOCK DATA — INSERT STATEMENTS
-- =============================================================================

-- --------------------------------------------------
-- ROLES
-- --------------------------------------------------
INSERT INTO roles (role_id, role_name, permissions) VALUES
(seq_role_id.NEXTVAL, 'Administrator',
 '{"dashboard":true,"partners":true,"transactions":true,"alerts":true,"admin":true}');

INSERT INTO roles (role_id, role_name, permissions) VALUES
(seq_role_id.NEXTVAL, 'Analyst',
 '{"dashboard":true,"partners":true,"transactions":true,"alerts":false,"admin":false}');

INSERT INTO roles (role_id, role_name, permissions) VALUES
(seq_role_id.NEXTVAL, 'Partner Manager',
 '{"dashboard":true,"partners":true,"transactions":false,"alerts":false,"admin":false}');

-- --------------------------------------------------
-- USERS
-- --------------------------------------------------
-- Passwords stored as bcrypt hashes (demo: all use "password123")
INSERT INTO users (user_id, full_name, email, password_hash, role_id, status) VALUES
(seq_user_id.NEXTVAL, 'Алексей Волков',    'a.volkov@bank.ru',     '$2y$10$demoHashAdmin000001', 1, 'active');

INSERT INTO users (user_id, full_name, email, password_hash, role_id, status) VALUES
(seq_user_id.NEXTVAL, 'Мария Смирнова',    'm.smirnova@bank.ru',   '$2y$10$demoHashAnalyst00001', 2, 'active');

INSERT INTO users (user_id, full_name, email, password_hash, role_id, status) VALUES
(seq_user_id.NEXTVAL, 'Дмитрий Козлов',   'd.kozlov@bank.ru',     '$2y$10$demoHashManager0001', 3, 'active');

-- --------------------------------------------------
-- PARTNERS
-- --------------------------------------------------
INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'ТехноРетейл ООО',   'ООО',  'active',   TO_DATE('2023-01-15','YYYY-MM-DD'), 'contact@technoretail.ru',  'Мария Смирнова',  1);

INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'ФинтехПэй АО',      'АО',   'active',   TO_DATE('2022-11-01','YYYY-MM-DD'), 'ops@fintechpay.ru',        'Дмитрий Козлов',  1);

INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'ЭзиШоп Партнеры',   'ООО',  'active',   TO_DATE('2023-03-20','YYYY-MM-DD'), 'info@easyshop-partners.ru','Мария Смирнова',  1);

INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'МаркетПлейс Рус',   'ООО',  'active',   TO_DATE('2023-06-10','YYYY-MM-DD'), 'baas@marketplace-rus.ru',  'Дмитрий Козлов',  1);

INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'КредитСервис ПАО',  'ПАО',  'inactive', TO_DATE('2022-08-05','YYYY-MM-DD'), 'cards@creditservice.ru',   'Мария Смирнова',  1);

INSERT INTO partners (partner_id, partner_name, legal_form, status, contract_date, contact_email, account_manager, created_by) VALUES
(seq_partner_id.NEXTVAL, 'ДиджиталБанк ООО',  'ООО',  'active',   TO_DATE('2024-01-22','YYYY-MM-DD'), 'tech@digitalbank.ru',      'Дмитрий Козлов',  1);

-- --------------------------------------------------
-- CARD PACKAGES
-- --------------------------------------------------
-- ТехноРетейл — 2 packages (2024 exhausted, 2025 active)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 1, 10000, TO_DATE('2024-01-01','YYYY-MM-DD'), TO_DATE('2024-12-31','YYYY-MM-DD'), 'active',   'Основной пакет 2024', 1);

INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 1,  5000, TO_DATE('2025-01-01','YYYY-MM-DD'), TO_DATE('2025-12-31','YYYY-MM-DD'), 'active',   'Расширение 2025', 1);

-- ФинтехПэй — 2 packages (2023 closed, 2024–2025 active)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 2,  2000, TO_DATE('2023-01-01','YYYY-MM-DD'), TO_DATE('2023-12-31','YYYY-MM-DD'), 'closed',   'Пилот 2023', 1);

INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 2,  8000, TO_DATE('2024-06-01','YYYY-MM-DD'), TO_DATE('2025-05-31','YYYY-MM-DD'), 'active',   'Основной пакет 2024–2025', 1);

-- ЭзиШоп — 2 packages (2024 exhausted, 2025 active)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 3,  3000, TO_DATE('2024-03-01','YYYY-MM-DD'), TO_DATE('2025-02-28','YYYY-MM-DD'), 'exhausted','Пакет 2024 исчерпан', 1);

INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 3,  5000, TO_DATE('2025-03-01','YYYY-MM-DD'), TO_DATE('2026-02-28','YYYY-MM-DD'), 'active',   'Пакет 2025–2026', 1);

-- МаркетПлейс — 2 packages (2023 exhausted, 2024 active)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 4,  3000, TO_DATE('2023-06-01','YYYY-MM-DD'), TO_DATE('2024-05-31','YYYY-MM-DD'), 'exhausted','Стартовый пакет', 1);

INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 4,  7500, TO_DATE('2024-09-01','YYYY-MM-DD'), TO_DATE('2025-08-31','YYYY-MM-DD'), 'active',   'Расширенный пакет 2024', 1);

-- КредитСервис — 1 package (closed, inactive partner)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 5,  2000, TO_DATE('2023-01-01','YYYY-MM-DD'), TO_DATE('2023-12-31','YYYY-MM-DD'), 'closed',   'Партнер деактивирован', 1);

-- ДиджиталБанк — 1 large package (2025, growing)
INSERT INTO card_packages (package_id, partner_id, package_size, start_date, end_date, status, notes, created_by) VALUES
(seq_package_id.NEXTVAL, 6, 15000, TO_DATE('2025-01-01','YYYY-MM-DD'), TO_DATE('2025-12-31','YYYY-MM-DD'), 'active',   'Стратегический партнер — расширенный пакет', 1);

-- --------------------------------------------------
-- DWH SYNC LOG
-- --------------------------------------------------
INSERT INTO dwh_sync_log (sync_id, source_name, started_at, finished_at, status, rows_loaded) VALUES
(seq_sync_id.NEXTVAL, 'dwh_card_daily_agg',
 TO_TIMESTAMP('2026-05-07 02:00:00','YYYY-MM-DD HH24:MI:SS'),
 TO_TIMESTAMP('2026-05-07 02:04:31','YYYY-MM-DD HH24:MI:SS'),
 'SUCCESS', 4320);

INSERT INTO dwh_sync_log (sync_id, source_name, started_at, finished_at, status, rows_loaded) VALUES
(seq_sync_id.NEXTVAL, 'dwh_transaction_daily_agg',
 TO_TIMESTAMP('2026-05-07 02:05:00','YYYY-MM-DD HH24:MI:SS'),
 TO_TIMESTAMP('2026-05-07 02:09:47','YYYY-MM-DD HH24:MI:SS'),
 'SUCCESS', 12960);

INSERT INTO dwh_sync_log (sync_id, source_name, started_at, finished_at, status, rows_loaded) VALUES
(seq_sync_id.NEXTVAL, 'dwh_transaction_mcc_agg',
 TO_TIMESTAMP('2026-05-07 02:10:00','YYYY-MM-DD HH24:MI:SS'),
 TO_TIMESTAMP('2026-05-07 02:11:22','YYYY-MM-DD HH24:MI:SS'),
 'SUCCESS', 8190);

INSERT INTO dwh_sync_log (sync_id, source_name, started_at, finished_at, status, rows_loaded) VALUES
(seq_sync_id.NEXTVAL, 'dwh_transaction_country_agg',
 TO_TIMESTAMP('2026-05-06 02:00:00','YYYY-MM-DD HH24:MI:SS'),
 TO_TIMESTAMP('2026-05-06 02:01:55','YYYY-MM-DD HH24:MI:SS'),
 'FAILED', 0);

-- --------------------------------------------------
-- PACKAGE USAGE SNAPSHOTS (current / latest)
-- --------------------------------------------------
-- Package 1: ТехноРетейл 2024 — near capacity
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 1, 1, 9245, 755,  92.45, 1);
-- Package 2: ТехноРетейл 2025 — early
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 2, 1, 1832, 3168, 36.64, 1);
-- Package 3: ФинтехПэй closed 2023
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 3, 2, 2000, 0,    100.00, 1);
-- Package 4: ФинтехПэй 2024 active
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 4, 2, 6104, 1896, 76.30, 1);
-- Package 5: ЭзиШоп 2024 exhausted
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 5, 3, 3000, 0,    100.00, 1);
-- Package 6: ЭзиШоп 2025 active
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 6, 3, 1247, 3753, 24.94, 1);
-- Package 7: МаркетПлейс exhausted
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 7, 4, 3000, 0,    100.00, 1);
-- Package 8: МаркетПлейс 2024 active
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 8, 4, 4891, 2609, 65.21, 1);
-- Package 9: КредитСервис closed
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 9, 5, 2000, 0,    100.00, 1);
-- Package 10: ДиджиталБанк — large, growing fast
INSERT INTO package_usage_snapshot (snapshot_date, package_id, partner_id, issued_cards, remaining_cards, usage_percent, source_sync_id) VALUES
(TO_DATE('2026-05-07','YYYY-MM-DD'), 10, 6, 8732, 6268, 58.21, 1);

-- --------------------------------------------------
-- TRANSACTION DAILY AGG CACHE (last 30 days, active partners)
-- Generated block for 5 active partners × 30 days × RUB currency
-- Only last 7 days shown for brevity; full data would be generated by sync job
-- --------------------------------------------------
-- Partner 1: ТехноРетейл — ~950k RUB/day
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-07','YYYY-MM-DD'), 1, 'RUB', 1482,  963450.00,  650.10, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-06','YYYY-MM-DD'), 1, 'RUB', 1521,  988650.50,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-05','YYYY-MM-DD'), 1, 'RUB',  965,  627250.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-04','YYYY-MM-DD'), 1, 'RUB',  842,  547300.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-03','YYYY-MM-DD'), 1, 'RUB', 1390,  903500.00,  650.36, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-02','YYYY-MM-DD'), 1, 'RUB', 1415,  919750.50,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-01','YYYY-MM-DD'), 1, 'RUB', 1378,  896700.00,  650.73, 2);

-- Partner 2: ФинтехПэй — ~610k RUB/day
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-07','YYYY-MM-DD'), 2, 'RUB',  942,  612300.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-06','YYYY-MM-DD'), 2, 'RUB',  970,  630500.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-05','YYYY-MM-DD'), 2, 'RUB',  631,  410150.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-04','YYYY-MM-DD'), 2, 'RUB',  558,  362700.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-03','YYYY-MM-DD'), 2, 'RUB',  887,  576550.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-02','YYYY-MM-DD'), 2, 'RUB',  901,  585650.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-01','YYYY-MM-DD'), 2, 'RUB',  860,  559000.00,  650.00, 2);

-- Partner 3: ЭзиШоп — ~420k RUB/day
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-07','YYYY-MM-DD'), 3, 'RUB',  652,  423800.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-06','YYYY-MM-DD'), 3, 'RUB',  671,  436150.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-05','YYYY-MM-DD'), 3, 'RUB',  421,  273650.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-04','YYYY-MM-DD'), 3, 'RUB',  387,  251550.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-03','YYYY-MM-DD'), 3, 'RUB',  610,  396500.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-02','YYYY-MM-DD'), 3, 'RUB',  623,  404950.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-01','YYYY-MM-DD'), 3, 'RUB',  598,  388700.00,  650.00, 2);

-- Partner 4: МаркетПлейс — ~715k RUB/day
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-07','YYYY-MM-DD'), 4, 'RUB', 1102,  716300.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-06','YYYY-MM-DD'), 4, 'RUB', 1134,  737100.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-05','YYYY-MM-DD'), 4, 'RUB',  714,  464100.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-04','YYYY-MM-DD'), 4, 'RUB',  633,  411450.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-03','YYYY-MM-DD'), 4, 'RUB', 1050,  682500.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-02','YYYY-MM-DD'), 4, 'RUB', 1076,  699400.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-01','YYYY-MM-DD'), 4, 'RUB', 1033,  671450.00,  650.00, 2);

-- Partner 6: ДиджиталБанк — ~1.19M RUB/day (highest volume)
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-07','YYYY-MM-DD'), 6, 'RUB', 1831, 1190150.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-06','YYYY-MM-DD'), 6, 'RUB', 1889, 1227850.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-05','YYYY-MM-DD'), 6, 'RUB', 1193,  775450.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-04','YYYY-MM-DD'), 6, 'RUB', 1046,  679900.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-03','YYYY-MM-DD'), 6, 'RUB', 1750, 1137500.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-02','YYYY-MM-DD'), 6, 'RUB', 1810, 1176500.00,  650.00, 2);
INSERT INTO transaction_daily_agg_cache (agg_date, partner_id, currency, transaction_count, transaction_volume, avg_transaction_amount, source_sync_id) VALUES (TO_DATE('2026-05-01','YYYY-MM-DD'), 6, 'RUB', 1766, 1147900.00,  650.00, 2);

-- --------------------------------------------------
-- ALERTS CONFIG
-- --------------------------------------------------
INSERT INTO alerts_config (alert_id, alert_name, metric_code, threshold_value, condition_op, recipient_group, is_active, created_by) VALUES
(seq_alert_id.NEXTVAL, 'Package Near Exhaustion', 'USAGE_PCT', 90.00, 'GTE', 'baas-ops@bank.ru', 1, 1);

INSERT INTO alerts_config (alert_id, alert_name, metric_code, threshold_value, condition_op, recipient_group, is_active, created_by) VALUES
(seq_alert_id.NEXTVAL, 'Low Transaction Volume', 'TXN_VOLUME_DAILY', 200000.00, 'LT', 'baas-ops@bank.ru', 1, 1);

INSERT INTO alerts_config (alert_id, alert_name, metric_code, threshold_value, condition_op, recipient_group, is_active, created_by) VALUES
(seq_alert_id.NEXTVAL, 'DWH Sync Failed', 'SYNC_STATUS', 0, 'EQ', 'dba@bank.ru', 1, 1);

-- --------------------------------------------------
-- KPI SETTINGS
-- --------------------------------------------------
INSERT INTO partner_kpi_settings (kpi_id, partner_id, metric_code, target_value, period_type, valid_from, created_by) VALUES
(seq_kpi_id.NEXTVAL, 1, 'TXN_VOLUME_MONTHLY', 25000000.00, 'MONTHLY', TO_DATE('2026-01-01','YYYY-MM-DD'), 1);
INSERT INTO partner_kpi_settings (kpi_id, partner_id, metric_code, target_value, period_type, valid_from, created_by) VALUES
(seq_kpi_id.NEXTVAL, 2, 'TXN_VOLUME_MONTHLY', 16000000.00, 'MONTHLY', TO_DATE('2026-01-01','YYYY-MM-DD'), 1);
INSERT INTO partner_kpi_settings (kpi_id, partner_id, metric_code, target_value, period_type, valid_from, created_by) VALUES
(seq_kpi_id.NEXTVAL, 6, 'TXN_VOLUME_MONTHLY', 30000000.00, 'MONTHLY', TO_DATE('2026-01-01','YYYY-MM-DD'), 1);

COMMIT;

-- =============================================================================
-- SECTION 7: USEFUL ANALYTICAL QUERIES
-- =============================================================================

-- Q1: Dashboard KPIs
SELECT * FROM v_dashboard_kpis;

-- Q2: Current package usage per partner (for Partners page)
SELECT
    p.partner_id,
    p.partner_name,
    p.status,
    SUM(cp.package_size)     AS total_package_size,
    SUM(s.issued_cards)      AS total_issued,
    SUM(s.remaining_cards)   AS total_remaining,
    ROUND(SUM(s.issued_cards) / NULLIF(SUM(cp.package_size), 0) * 100, 2) AS overall_usage_pct
FROM partners p
JOIN card_packages cp ON cp.partner_id = p.partner_id
JOIN package_usage_snapshot s ON s.package_id = cp.package_id
    AND s.snapshot_date = (
        SELECT MAX(s2.snapshot_date) FROM package_usage_snapshot s2
        WHERE s2.package_id = cp.package_id
    )
GROUP BY p.partner_id, p.partner_name, p.status
ORDER BY overall_usage_pct DESC;

-- Q3: Transaction volume last 30 days by partner
SELECT
    p.partner_name,
    SUM(t.transaction_count)  AS txn_count,
    SUM(t.transaction_volume) AS txn_volume,
    AVG(t.avg_transaction_amount) AS avg_ticket
FROM transaction_daily_agg_cache t
JOIN partners p ON p.partner_id = t.partner_id
WHERE t.agg_date >= TRUNC(SYSDATE) - 30
GROUP BY p.partner_id, p.partner_name
ORDER BY txn_volume DESC;

-- Q4: Daily transaction trend (all partners, last 30 days)
SELECT
    agg_date,
    SUM(transaction_count)  AS total_txn_count,
    SUM(transaction_volume) AS total_volume
FROM transaction_daily_agg_cache
WHERE agg_date >= TRUNC(SYSDATE) - 30
GROUP BY agg_date
ORDER BY agg_date;

-- Q5: MCC breakdown (from DWH)
SELECT
    mcc,
    mcc_description,
    SUM(transaction_count)  AS txn_count,
    SUM(transaction_volume) AS txn_volume
FROM dwh_transaction_mcc_agg
WHERE agg_date >= TRUNC(SYSDATE) - 30
GROUP BY mcc, mcc_description
ORDER BY txn_volume DESC;

-- Q6: Packages expiring in the next 60 days
SELECT
    p.partner_name,
    cp.package_id,
    cp.package_size,
    cp.end_date,
    s.remaining_cards,
    s.usage_percent,
    cp.end_date - TRUNC(SYSDATE) AS days_until_expiry
FROM card_packages cp
JOIN partners p ON p.partner_id = cp.partner_id
JOIN package_usage_snapshot s ON s.package_id = cp.package_id
    AND s.snapshot_date = (
        SELECT MAX(s2.snapshot_date) FROM package_usage_snapshot s2
        WHERE s2.package_id = cp.package_id
    )
WHERE cp.status = 'active'
  AND cp.end_date BETWEEN TRUNC(SYSDATE) AND TRUNC(SYSDATE) + 60
ORDER BY cp.end_date;
