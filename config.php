<?php
/**
 * =============================================================================
 * config.php — Supabase-only configuration
 * BaaS Partner Monitoring System
 *
 * This is the SINGLE SOURCE OF TRUTH for all connection settings.
 * Loaded by _common.php via:  $cfg = require_once 'config.php';
 *
 * SECURITY:
 *   - Do NOT commit this file with real keys.
 *   - The server-side code SHOULD use the Supabase service_role secret key,
 *     not the public anon key. The service_role key bypasses RLS, which is
 *     what we want for a trusted server process. Get it from:
 *         Supabase Dashboard → Project Settings → API → service_role (secret)
 *   - Keep this file outside the web document root, or block direct HTTP
 *     access to *.php includes with a webserver rule.
 * =============================================================================
 */

return [

    // =========================================================================
    // SECTION 1 — APPLICATION
    // =========================================================================
    'app' => [
        'name'          => 'BaaS Monitor',
        'version'       => '2.1',
        'timezone'      => 'Asia/Baku',
        'locale'        => 'az_AZ',
        'currency'      => 'AZN',
        'currency_sym'  => '₼',
    ],

    // =========================================================================
    // SECTION 2 — SUPABASE CONNECTION
    //
    // service_role_key is REQUIRED for production. The anon key works too but
    // requires permissive RLS policies (any browser with the key can read/write
    // every row). Use service_role on the server + lock RLS down.
    //
    // Supabase Dashboard → Settings → API:
    //   Project URL         → url
    //   service_role secret → service_role_key
    // =========================================================================
    'supabase' => [
        'url'              => 'https://icakohbufchlabkiupme.supabase.co',
        // PASTE THE service_role SECRET HERE BEFORE DEPLOYING TO PRODUCTION.
        // For initial bring-up you can paste the anon key, but switch to
        // service_role once supabase_migration_v4.sql has tightened RLS.
        'service_role_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImljYWtvaGJ1ZmNobGFia2l1cG1lIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODU3ODU5NSwiZXhwIjoyMDk0MTU0NTk1fQ.JQfZZAzGJ9PUlZPMHucfpP_FAOIs3NFNg4ZBEOB3NB4',
        'storage_bucket'   => 'partner-docs',
        'request_timeout'  => 10,
        'connect_timeout'  => 5,
    ],

    // =========================================================================
    // SECTION 3 — CACHE
    // =========================================================================
    'cache' => [
        'enabled'               => true,
        'driver'                => 'apcu',
        'file_path'             => __DIR__ . '/cache',
        'ttl_dashboard_kpis'    => 300,
        'ttl_partner_list'      => 600,
        'ttl_package_usage'     => 300,
    ],

    // =========================================================================
    // SECTION 4 — SECURITY
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
    // SECTION 5 — FEATURE FLAGS
    // =========================================================================
    'features' => [
        'dashboard_enabled'      => true,
        'partners_enabled'       => true,
        'transactions_enabled'   => true,
        'alerts_enabled'         => true,
        'admin_panel_enabled'    => true,
        'export_csv_enabled'     => true,
        'kpi_targets_enabled'    => true,
    ],

];
