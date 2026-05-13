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
 *   1. Set 'mode' to:
 *        'mock'     — demo, PHP-arrays, no DB required
 *        'supabase' — Supabase (PostgreSQL), fill supabase section below
 *        'oracle'   — Oracle 19c+, fill db_monitor / db_dwh sections
 *   2. For Supabase: paste Project URL and anon key from
 *        Supabase Dashboard → Settings → API
 *   3. For Oracle: fill in db_monitor and db_dwh credentials.
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
        'timezone'      => 'Asia/Baku',
        'locale'        => 'az_AZ',
        'currency'      => 'AZN',
        'currency_sym'  => '₼',
    ],

    // =========================================================================
    // SECTION 2 — DATA SOURCE MODE
    //   'mock'     → PHP arrays from _common.php (no DB, demo only)
    //   'supabase' → Supabase REST API (credentials in Section 3 below)
    //   'oracle'   → Oracle PDO (credentials in Sections 4–5 below)
    // =========================================================================
    'mode' => 'supabase',   // ← 'mock' | 'supabase' | 'oracle'

    // =========================================================================
    // SECTION 3 — SUPABASE CONNECTION
    //
    // Получить: Supabase Dashboard → Settings → API
    //   Project URL  → url
    //   anon public  → anon_key
    //
    // Таблицы: partners, packages, sys_users, partner_docs,
    //          fee_packages, mcc_data, country_data
    // =========================================================================
    'supabase' => [
        'url'      => 'https://icakohbufchlabkiupme.supabase.co',
        'anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImljYWtvaGJ1ZmNobGFia2l1cG1lIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Nzg1Nzg1OTUsImV4cCI6MjA5NDE1NDU5NX0.mzDEo117hBkPp9rx-sHXquBASNci8VZ7JzMpsME8NOI',
    ],

    // =========================================================================
    // SECTION 4 — MONITORING SYSTEM DATABASE (Oracle — primary)
    // Используется только при mode = 'oracle'
    // =========================================================================
    'db_monitor' => [
        'dsn'      => 'oci:dbname=//db-host.bank.az:1521/BAASDB',
        'user'     => 'BAAS_MONITOR',
        'password' => 'CHANGE_ME_IN_PRODUCTION',
        'schema'   => 'BAAS_MONITOR',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
        ],
        'connect_timeout_sec' => 5,
        'query_timeout_sec'   => 30,
        'max_retries'         => 2,
    ],

    // =========================================================================
    // SECTION 5 — DATA WAREHOUSE DATABASE (Oracle — secondary)
    // Используется только при mode = 'oracle'
    // =========================================================================
    'db_dwh' => [
        'access_type' => 'dblink',
        'dsn'         => 'oci:dbname=//dwh-host.bank.az:1521/DWHDB',
        'user'        => 'BAAS_DWH_READER',
        'password'    => 'CHANGE_ME_IN_PRODUCTION',
        'schema'      => 'DWH',
        'dblink_name' => 'BAAS_DWH_LINK',
        'options'     => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
        'connect_timeout_sec' => 10,
        'query_timeout_sec'   => 60,
    ],

    // =========================================================================
    // SECTION 6 — TABLE / VIEW NAMES (Oracle mode only)
    // =========================================================================
    'tables' => [
        'partners'                    => 'BAAS_MONITOR.PARTNERS',
        'card_packages'               => 'BAAS_MONITOR.CARD_PACKAGES',
        'package_usage_snapshot'      => 'BAAS_MONITOR.PACKAGE_USAGE_SNAPSHOT',
        'transaction_daily_agg_cache' => 'BAAS_MONITOR.TRANSACTION_DAILY_AGG_CACHE',
        'partner_kpi_settings'        => 'BAAS_MONITOR.PARTNER_KPI_SETTINGS',
        'users'                       => 'BAAS_MONITOR.USERS',
        'roles'                       => 'BAAS_MONITOR.ROLES',
        'alerts_config'               => 'BAAS_MONITOR.ALERTS_CONFIG',
        'alerts_log'                  => 'BAAS_MONITOR.ALERTS_LOG',
        'dwh_sync_log'                => 'BAAS_MONITOR.DWH_SYNC_LOG',
        'v_active_package_usage'      => 'BAAS_MONITOR.V_ACTIVE_PACKAGE_USAGE',
        'v_partner_txn_summary_30d'   => 'BAAS_MONITOR.V_PARTNER_TXN_SUMMARY_30D',
        'v_dashboard_kpis'            => 'BAAS_MONITOR.V_DASHBOARD_KPIS',
        'dwh_cards'                   => 'DWH.CARDS',
        'dwh_card_daily_agg'          => 'DWH.CARD_DAILY_AGG',
        'dwh_transactions'            => 'DWH.TRANSACTIONS',
        'dwh_transaction_daily_agg'   => 'DWH.TRANSACTION_DAILY_AGG',
        'dwh_transaction_mcc_agg'     => 'DWH.TRANSACTION_MCC_AGG',
        'dwh_transaction_country_agg' => 'DWH.TRANSACTION_COUNTRY_AGG',
    ],

    // =========================================================================
    // SECTION 7 — SYNC / ETL (Oracle mode only)
    // =========================================================================
    'sync' => [
        'card_daily_agg_interval_hours'  => 3,
        'txn_daily_agg_interval_hours'   => 1,
        'mcc_agg_interval_hours'         => 24,
        'country_agg_interval_hours'     => 24,
        'card_lookback_days'             => 7,
        'txn_lookback_days'              => 3,
        'batch_size'                     => 500,
        'retry_on_failure'               => true,
        'max_retry_attempts'             => 3,
        'retry_delay_seconds'            => 60,
        'stale_threshold_hours'          => 6,
    ],

    // =========================================================================
    // SECTION 8 — CACHE
    // =========================================================================
    'cache' => [
        'enabled'               => true,
        'driver'                => 'apcu',
        'file_path'             => __DIR__ . '/cache',
        'ttl_dashboard_kpis'    => 300,
        'ttl_partner_list'      => 600,
        'ttl_package_usage'     => 300,
        'ttl_txn_daily_agg'     => 120,
        'ttl_mcc_country_agg'   => 3600,
    ],

    // =========================================================================
    // SECTION 9 — SECURITY
    // =========================================================================
    'security' => [
        'session_lifetime_minutes'    => 60,
        'session_regenerate_on_login' => true,
        'password_algo'               => PASSWORD_BCRYPT,
        'password_cost'               => 12,
        'csrf_token_lifetime'         => 3600,
        'audit_log_enabled'           => true,
    ],

    // =========================================================================
    // SECTION 10 — FEATURE FLAGS
    // =========================================================================
    'features' => [
        'dashboard_enabled'      => true,
        'partners_enabled'       => true,
        'transactions_enabled'   => true,
        'alerts_enabled'         => true,
        'admin_panel_enabled'    => true,
        'dwh_drilldown_enabled'  => true,
        'export_csv_enabled'     => true,
        'kpi_targets_enabled'    => true,
    ],

];
