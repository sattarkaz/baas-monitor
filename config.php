<?php
/**
 * =============================================================================
 * config.php — Data Source Configuration
 * BaaS Partner Monitoring System
 *
 * This is the SINGLE SOURCE OF TRUTH for all external connections.
 * Edit only this file when changing databases, schemas, or sync settings.
 * All other files load this config via:  require_once 'config.php';
 * =============================================================================
 *
 * QUICK-START:
 *   1. Set 'mode' to 'oracle' when Oracle is available; keep 'mock' for demo.
 *   2. Fill in db_monitor and db_dwh sections with real credentials.
 *   3. Adjust table names if your schema / DB-link names differ.
 *   4. Tune sync intervals and cache TTL to match your environment.
 *
 * FILE MUST NOT be committed to version control with real passwords.
 * Use a .env file or vault to inject credentials in production.
 * =============================================================================
 */

return [

    // =========================================================================
    // SECTION 1 — APPLICATION
    // =========================================================================
    'app' => [
        'name'          => 'BaaS Monitor',
        'version'       => '2.0',
        'timezone'      => 'Asia/Baku',          // PHP date_default_timezone_set
        'locale'        => 'az_AZ',
        'currency'      => 'AZN',                // base display currency
        'currency_sym'  => '₼',
    ],

    // =========================================================================
    // SECTION 2 — DATA SOURCE MODE
    //   'mock'   → use PHP arrays from _common.php (no DB required, demo only)
    //   'oracle' → connect to Oracle using credentials in db_monitor / db_dwh
    // =========================================================================
    'mode' => 'mock',   // ← change to 'oracle' in production

    // =========================================================================
    // SECTION 3 — MONITORING SYSTEM DATABASE (Oracle — primary)
    //
    // Stores: partners, card_packages, package_usage_snapshot,
    //         transaction_daily_agg_cache, users, roles, alerts_*, dwh_sync_log
    //
    // Driver: pdo_oci  (requires php-oci8 or php-pdo-oci extension)
    // =========================================================================
    'db_monitor' => [
        // Oracle Easy Connect string  →  //host:port/service_name
        // Or TNS alias if tnsnames.ora is configured  →  BAASDB
        'dsn'           => 'oci:dbname=//db-host.bank.az:1521/BAASDB',

        'user'          => 'BAAS_MONITOR',
        'password'      => 'CHANGE_ME_IN_PRODUCTION',
        'schema'        => 'BAAS_MONITOR',       // default schema / owner prefix

        // PDO options
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,   // true = connection pooling
        ],

        // Connection pool / retry
        'connect_timeout_sec'   => 5,
        'query_timeout_sec'     => 30,
        'max_retries'           => 2,
    ],

    // =========================================================================
    // SECTION 4 — DATA WAREHOUSE DATABASE (Oracle — secondary)
    //
    // Stores: dwh_cards, dwh_card_daily_agg, dwh_transactions,
    //         dwh_transaction_daily_agg, dwh_transaction_mcc_agg,
    //         dwh_transaction_country_agg
    //
    // ACCESS STRATEGY (choose one and set 'access_type'):
    //   'dblink'  → objects accessed via a DB link from the monitoring DB
    //               e.g.  SELECT … FROM DWH.TRANSACTION_DAILY_AGG@BAAS_DWH_LINK
    //   'direct'  → separate PDO connection to the DWH Oracle instance
    //   'view'    → DWH tables are exposed as views inside the monitoring schema
    // =========================================================================
    'db_dwh' => [
        'access_type'   => 'dblink',             // 'dblink' | 'direct' | 'view'

        // Used only when access_type = 'direct'
        'dsn'           => 'oci:dbname=//dwh-host.bank.az:1521/DWHDB',
        'user'          => 'BAAS_DWH_READER',
        'password'      => 'CHANGE_ME_IN_PRODUCTION',
        'schema'        => 'DWH',

        // DB link name (used when access_type = 'dblink')
        // Must exist in the monitoring DB: CREATE DATABASE LINK BAAS_DWH_LINK …
        'dblink_name'   => 'BAAS_DWH_LINK',

        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],

        'connect_timeout_sec'   => 10,
        'query_timeout_sec'     => 60,   // DWH queries can be heavier
    ],

    // =========================================================================
    // SECTION 5 — TABLE / VIEW NAMES
    //
    // Override individual entries if your naming convention differs.
    // All names are used as-is in SQL: SELECT … FROM {table_name}
    // Include schema prefix and DB-link suffix where applicable.
    // =========================================================================
    'tables' => [

        // ── Monitoring system tables (owned by BAAS_MONITOR schema) ──────────
        'partners'                  => 'BAAS_MONITOR.PARTNERS',
        'card_packages'             => 'BAAS_MONITOR.CARD_PACKAGES',
        'package_usage_snapshot'    => 'BAAS_MONITOR.PACKAGE_USAGE_SNAPSHOT',
        'transaction_daily_agg_cache' => 'BAAS_MONITOR.TRANSACTION_DAILY_AGG_CACHE',
        'partner_kpi_settings'      => 'BAAS_MONITOR.PARTNER_KPI_SETTINGS',
        'users'                     => 'BAAS_MONITOR.USERS',
        'roles'                     => 'BAAS_MONITOR.ROLES',
        'alerts_config'             => 'BAAS_MONITOR.ALERTS_CONFIG',
        'alerts_log'                => 'BAAS_MONITOR.ALERTS_LOG',
        'dwh_sync_log'              => 'BAAS_MONITOR.DWH_SYNC_LOG',

        // ── Monitoring system views ──────────────────────────────────────────
        'v_active_package_usage'    => 'BAAS_MONITOR.V_ACTIVE_PACKAGE_USAGE',
        'v_partner_txn_summary_30d' => 'BAAS_MONITOR.V_PARTNER_TXN_SUMMARY_30D',
        'v_dashboard_kpis'          => 'BAAS_MONITOR.V_DASHBOARD_KPIS',

        // ── DWH objects — dblink suffix appended automatically when
        //    db_dwh.access_type = 'dblink' ────────────────────────────────────
        'dwh_cards'                     => 'DWH.CARDS',
        'dwh_card_daily_agg'            => 'DWH.CARD_DAILY_AGG',
        'dwh_transactions'              => 'DWH.TRANSACTIONS',
        'dwh_transaction_daily_agg'     => 'DWH.TRANSACTION_DAILY_AGG',
        'dwh_transaction_mcc_agg'       => 'DWH.TRANSACTION_MCC_AGG',
        'dwh_transaction_country_agg'   => 'DWH.TRANSACTION_COUNTRY_AGG',
    ],

    // =========================================================================
    // SECTION 6 — SYNC / ETL SETTINGS
    //
    // Controls how often aggregated data is pulled from DWH into the cache.
    // The actual job scheduler (cron / Oracle DBMS_SCHEDULER) must be
    // configured separately and call the sync endpoint or script.
    // =========================================================================
    'sync' => [
        // Scheduled sync intervals (hours)
        'card_daily_agg_interval_hours'         => 3,    // dwh_card_daily_agg → package_usage_snapshot
        'txn_daily_agg_interval_hours'          => 1,    // dwh_transaction_daily_agg → transaction_daily_agg_cache
        'mcc_agg_interval_hours'                => 24,   // dwh_transaction_mcc_agg  (cached separately)
        'country_agg_interval_hours'            => 24,   // dwh_transaction_country_agg

        // Lookback window for each sync (days)
        'card_lookback_days'                    => 7,
        'txn_lookback_days'                     => 3,

        // Batch size (rows per INSERT in cache load)
        'batch_size'                            => 500,

        // Retry failed syncs
        'retry_on_failure'                      => true,
        'max_retry_attempts'                    => 3,
        'retry_delay_seconds'                   => 60,

        // Alert if sync has not completed within N hours
        'stale_threshold_hours'                 => 6,
    ],

    // =========================================================================
    // SECTION 7 — CACHE
    //
    // PHP-level in-memory / APCu cache for dashboard KPIs.
    // Reduces round-trips to Oracle on high-traffic pages.
    // =========================================================================
    'cache' => [
        'enabled'       => true,
        'driver'        => 'apcu',              // 'apcu' | 'file' | 'none'
        'file_path'     => __DIR__ . '/cache',  // used when driver = 'file'

        // TTL per data type (seconds)
        'ttl_dashboard_kpis'        => 300,     //  5 min
        'ttl_partner_list'          => 600,     // 10 min
        'ttl_package_usage'         => 300,     //  5 min
        'ttl_txn_daily_agg'         => 120,     //  2 min
        'ttl_mcc_country_agg'       => 3600,    // 60 min
    ],

    // =========================================================================
    // SECTION 8 — SECURITY
    // =========================================================================
    'security' => [
        // Session
        'session_lifetime_minutes'      => 60,
        'session_regenerate_on_login'   => true,

        // Password hashing (PHP password_hash / password_verify)
        'password_algo'                 => PASSWORD_BCRYPT,
        'password_cost'                 => 12,

        // CSRF token lifetime (seconds)
        'csrf_token_lifetime'           => 3600,

        // Audit log: write every data-mutating action to dwh_sync_log
        'audit_log_enabled'             => true,
    ],

    // =========================================================================
    // SECTION 9 — FEATURE FLAGS
    // Toggle individual features without code changes.
    // =========================================================================
    'features' => [
        'dashboard_enabled'             => true,
        'partners_enabled'              => true,
        'transactions_enabled'          => true,
        'alerts_enabled'                => true,
        'admin_panel_enabled'           => true,
        'dwh_drilldown_enabled'         => true,   // on-demand DWH queries
        'export_csv_enabled'            => true,
        'kpi_targets_enabled'           => true,
    ],

];
