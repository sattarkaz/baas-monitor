<?php
// =============================================================================
// _common.php — Shared layout, authentication, and mock data layer
// BaaS Partner Monitoring System
//
// All connection settings, table names, and feature flags come from config.php.
// Do NOT hard-code credentials here — edit config.php instead.
// =============================================================================

// ----------------------------------------------------------------------------
// LOAD CONFIGURATION
// config.php is the single source of truth for data source settings.
// ----------------------------------------------------------------------------
$cfg = require_once __DIR__ . '/config.php';

// Convenience shortcuts derived from config
define('APP_NAME',    $cfg['app']['name']);
define('APP_VERSION', $cfg['app']['version']);
define('USE_MOCK_DATA', $cfg['mode'] === 'mock');

// Set timezone from config
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Baku');

// ----------------------------------------------------------------------------
// DATABASE CONNECTION (used when USE_MOCK_DATA = false)
// Reads DSN / user / password from config.php → db_monitor section.
// A second connection for DWH is established on demand via get_dwh_pdo().
// ----------------------------------------------------------------------------
function get_monitor_pdo(): PDO {
    global $cfg;
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $db = $cfg['db_monitor'];
    $pdo = new PDO($db['dsn'], $db['user'], $db['password'], $db['options'] ?? []);
    return $pdo;
}

function get_dwh_pdo(): PDO {
    global $cfg;
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if ($cfg['db_dwh']['access_type'] !== 'direct') {
        throw new RuntimeException(
            'DWH PDO requested but access_type is "' .
            $cfg['db_dwh']['access_type'] . '". Use DB-link or view instead.'
        );
    }
    $db = $cfg['db_dwh'];
    $pdo = new PDO($db['dsn'], $db['user'], $db['password'], $db['options'] ?? []);
    return $pdo;
}

/**
 * Resolve a table/view name from config.
 * Automatically appends the DB-link suffix for DWH objects when access_type = 'dblink'.
 *
 * Usage:  tbl('dwh_transaction_daily_agg')
 *         → "DWH.TRANSACTION_DAILY_AGG@BAAS_DWH_LINK"
 */
function tbl(string $key): string {
    global $cfg;
    $name = $cfg['tables'][$key] ?? $key;
    $isDwh = str_starts_with($key, 'dwh_');
    if ($isDwh && ($cfg['db_dwh']['access_type'] ?? '') === 'dblink') {
        $link = $cfg['db_dwh']['dblink_name'] ?? 'BAAS_DWH_LINK';
        return $name . '@' . $link;
    }
    return $name;
}

// ----------------------------------------------------------------------------
// SESSION & AUTH
// ----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_auth() {
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function current_user_role(): string {
    return strtolower($_SESSION['user']['role'] ?? 'viewer');
}

function is_admin(): bool {
    return current_user_role() === 'administrator';
}

// ----------------------------------------------------------------------------
// MOCK DATA (mirrors Oracle query result sets)
// In production each array below is replaced by the Oracle query shown above it
// ----------------------------------------------------------------------------

// -- Oracle query: SELECT * FROM partners ORDER BY partner_name
// New fields: voen, signatory_name, signatory_position, bank_name, bank_account,
//             bank_code, legal_address, contact_phone
$MOCK_PARTNERS = [
    [
        'partner_id'          => 1,
        'partner_name'        => 'AzərTech MMC',
        'legal_form'          => 'MMC',
        'status'              => 'active',
        'contract_date'       => '15.01.2023',
        'contact_email'       => 'contact@azartech.az',
        'contact_phone'       => '+994 12 555 01 01',
        'account_manager'     => 'Aynur Həsənova',
        'voen'                => '1234567890',
        'signatory_name'      => 'Rəşad Məmmədov',
        'signatory_position'  => 'Direktor',
        'bank_name'           => 'ABB Bank',
        'bank_account'        => 'AZ21NABZ00000000137010001944',
        'bank_code'           => '505044',
        'legal_address'       => 'Bakı ş., Nəsimi r., Füzuli küç. 12, AZ1000',
    ],
    [
        'partner_id'          => 2,
        'partner_name'        => 'FinTex ASC',
        'legal_form'          => 'ASC',
        'status'              => 'active',
        'contract_date'       => '01.11.2022',
        'contact_email'       => 'ops@fintex.az',
        'contact_phone'       => '+994 12 555 02 02',
        'account_manager'     => 'Elçin Quliyev',
        'voen'                => '2345678901',
        'signatory_name'      => 'Nigar Əliyeva',
        'signatory_position'  => 'İcraçı Direktor',
        'bank_name'           => 'Kapital Bank',
        'bank_account'        => 'AZ77AIIB38080000001249251',
        'bank_code'           => '200004',
        'legal_address'       => 'Bakı ş., Sabail r., İstiqlaliyyət küç. 67, AZ1001',
    ],
    [
        'partner_id'          => 3,
        'partner_name'        => 'EasyShop Tərəfdaşlar MMC',
        'legal_form'          => 'MMC',
        'status'              => 'active',
        'contract_date'       => '20.03.2023',
        'contact_email'       => 'info@easyshop.az',
        'contact_phone'       => '+994 55 555 03 03',
        'account_manager'     => 'Aynur Həsənova',
        'voen'                => '3456789012',
        'signatory_name'      => 'Tural İsmayılov',
        'signatory_position'  => 'Baş Direktor',
        'bank_name'           => 'Xalq Bank',
        'bank_account'        => 'AZ04UBAZ04003019449900393',
        'bank_code'           => '210005',
        'legal_address'       => 'Bakı ş., Xətai r., Hüseyn Cavid pr. 40, AZ1073',
    ],
    [
        'partner_id'          => 4,
        'partner_name'        => 'MarketYer Azərbaycan MMC',
        'legal_form'          => 'MMC',
        'status'              => 'active',
        'contract_date'       => '10.06.2023',
        'contact_email'       => 'baas@marketyer.az',
        'contact_phone'       => '+994 70 555 04 04',
        'account_manager'     => 'Elçin Quliyev',
        'voen'                => '4567890123',
        'signatory_name'      => 'Sevinc Babayeva',
        'signatory_position'  => 'Direktor',
        'bank_name'           => 'ABB Bank',
        'bank_account'        => 'AZ21NABZ00000000137010009871',
        'bank_code'           => '505044',
        'legal_address'       => 'Bakı ş., Binəqədi r., Binəqədi şossesi 100, AZ1102',
    ],
    [
        'partner_id'          => 5,
        'partner_name'        => 'KreditXidmət SC',
        'legal_form'          => 'SC',
        'status'              => 'inactive',
        'contract_date'       => '05.08.2022',
        'contact_email'       => 'cards@kreditxidmet.az',
        'contact_phone'       => '+994 12 555 05 05',
        'account_manager'     => 'Aynur Həsənova',
        'voen'                => '5678901234',
        'signatory_name'      => 'Fərid Nəsirov',
        'signatory_position'  => 'Müdir',
        'bank_name'           => 'PAŞA Bank',
        'bank_account'        => 'AZ96PAHA40370019002000011',
        'bank_code'           => '505754',
        'legal_address'       => 'Bakı ş., Yasamal r., Atatürk pr. 98, AZ1069',
    ],
    [
        'partner_id'          => 6,
        'partner_name'        => 'RəqəmsalBank MMC',
        'legal_form'          => 'MMC',
        'status'              => 'active',
        'contract_date'       => '22.01.2024',
        'contact_email'       => 'tech@reqemselbank.az',
        'contact_phone'       => '+994 51 555 06 06',
        'account_manager'     => 'Elçin Quliyev',
        'voen'                => '6789012345',
        'signatory_name'      => 'Kamran Hüseynov',
        'signatory_position'  => 'Baş İcraçı Direktor',
        'bank_name'           => 'Kapital Bank',
        'bank_account'        => 'AZ77AIIB38080000001249999',
        'bank_code'           => '200004',
        'legal_address'       => 'Bakı ş., Nərimanov r., Əliağa Vahid küç. 8, AZ1065',
    ],
];

// -- Oracle query: SELECT cp.*, s.issued_cards, s.remaining_cards, s.usage_percent
//    FROM card_packages cp JOIN package_usage_snapshot s ON ...
$MOCK_PACKAGES = [
    ['package_id' => 1,  'partner_id' => 1, 'package_size' => 10000, 'start_date' => '01.01.2024', 'end_date' => '31.12.2024', 'status' => 'active',    'issued_cards' => 9245,  'remaining_cards' => 755,   'usage_percent' => 92.45],
    ['package_id' => 2,  'partner_id' => 1, 'package_size' =>  5000, 'start_date' => '01.01.2025', 'end_date' => '31.12.2025', 'status' => 'active',    'issued_cards' => 1832,  'remaining_cards' => 3168,  'usage_percent' => 36.64],
    ['package_id' => 3,  'partner_id' => 2, 'package_size' =>  2000, 'start_date' => '01.01.2023', 'end_date' => '31.12.2023', 'status' => 'closed',    'issued_cards' => 2000,  'remaining_cards' => 0,     'usage_percent' => 100.00],
    ['package_id' => 4,  'partner_id' => 2, 'package_size' =>  8000, 'start_date' => '01.06.2024', 'end_date' => '31.05.2025', 'status' => 'active',    'issued_cards' => 6104,  'remaining_cards' => 1896,  'usage_percent' => 76.30],
    ['package_id' => 5,  'partner_id' => 3, 'package_size' =>  3000, 'start_date' => '01.03.2024', 'end_date' => '28.02.2025', 'status' => 'exhausted', 'issued_cards' => 3000,  'remaining_cards' => 0,     'usage_percent' => 100.00],
    ['package_id' => 6,  'partner_id' => 3, 'package_size' =>  5000, 'start_date' => '01.03.2025', 'end_date' => '28.02.2026', 'status' => 'active',    'issued_cards' => 1247,  'remaining_cards' => 3753,  'usage_percent' => 24.94],
    ['package_id' => 7,  'partner_id' => 4, 'package_size' =>  3000, 'start_date' => '01.06.2023', 'end_date' => '31.05.2024', 'status' => 'exhausted', 'issued_cards' => 3000,  'remaining_cards' => 0,     'usage_percent' => 100.00],
    ['package_id' => 8,  'partner_id' => 4, 'package_size' =>  7500, 'start_date' => '01.09.2024', 'end_date' => '31.08.2025', 'status' => 'active',    'issued_cards' => 4891,  'remaining_cards' => 2609,  'usage_percent' => 65.21],
    ['package_id' => 9,  'partner_id' => 5, 'package_size' =>  2000, 'start_date' => '01.01.2023', 'end_date' => '31.12.2023', 'status' => 'closed',    'issued_cards' => 2000,  'remaining_cards' => 0,     'usage_percent' => 100.00],
    ['package_id' => 10, 'partner_id' => 6, 'package_size' => 15000, 'start_date' => '01.01.2025', 'end_date' => '31.12.2025', 'status' => 'active',    'issued_cards' => 8732,  'remaining_cards' => 6268,  'usage_percent' => 58.21],
];

// -- Oracle query: SELECT * FROM v_partner_summary (aggregated per partner)
$MOCK_PARTNER_SUMMARY = [
    1 => ['total_pkg_size' => 15000, 'total_issued' => 11077, 'total_remaining' => 3923, 'avg_usage_pct' => 64.55, 'txn_volume_30d' => 28450230.50, 'txn_count_30d' => 45823],
    2 => ['total_pkg_size' => 10000, 'total_issued' =>  8104, 'total_remaining' => 1896, 'avg_usage_pct' => 81.04, 'txn_volume_30d' => 18230540.75, 'txn_count_30d' => 31402],
    3 => ['total_pkg_size' =>  8000, 'total_issued' =>  4247, 'total_remaining' => 3753, 'avg_usage_pct' => 53.09, 'txn_volume_30d' => 12580100.00, 'txn_count_30d' => 22815],
    4 => ['total_pkg_size' => 10500, 'total_issued' =>  7891, 'total_remaining' => 2609, 'avg_usage_pct' => 75.15, 'txn_volume_30d' => 21390875.25, 'txn_count_30d' => 38201],
    5 => ['total_pkg_size' =>  2000, 'total_issued' =>  2000, 'total_remaining' =>    0, 'avg_usage_pct' => 100.0, 'txn_volume_30d' =>         0.0, 'txn_count_30d' =>     0],
    6 => ['total_pkg_size' => 15000, 'total_issued' =>  8732, 'total_remaining' => 6268, 'avg_usage_pct' => 58.21, 'txn_volume_30d' => 35620900.00, 'txn_count_30d' => 52140],
];

// -- Oracle query: SELECT mcc, SUM(txn_count), SUM(txn_volume) FROM dwh_transaction_mcc_agg GROUP BY mcc
$MOCK_MCC = [
    ['mcc' => '5411', 'label' => 'Продуктовые магазины',     'txn_count' => 28451, 'txn_volume' => 31250800.00],
    ['mcc' => '5812', 'label' => 'Рестораны и кафе',          'txn_count' => 21302, 'txn_volume' => 14820300.50],
    ['mcc' => '5732', 'label' => 'Электроника',               'txn_count' =>  8920, 'txn_volume' => 22140500.00],
    ['mcc' => '5541', 'label' => 'Автозаправки',              'txn_count' => 15673, 'txn_volume' => 10200450.25],
    ['mcc' => '4814', 'label' => 'Телекоммуникации',          'txn_count' =>  9840, 'txn_volume' =>  5830200.75],
    ['mcc' => '7011', 'label' => 'Отели и размещение',        'txn_count' =>  3210, 'txn_volume' =>  8940100.00],
    ['mcc' => '5999', 'label' => 'Прочая розница',            'txn_count' => 12804, 'txn_volume' =>  7620400.50],
];

// -- Oracle query: SELECT merchant_country, SUM(...) FROM dwh_transaction_country_agg GROUP BY merchant_country
$MOCK_COUNTRY = [
    ['code' => 'RU', 'name' => 'Россия',      'flag' => '🇷🇺', 'txn_count' => 68204, 'txn_volume' => 78450200.00],
    ['code' => 'KZ', 'name' => 'Казахстан',   'flag' => '🇰🇿', 'txn_count' => 12340, 'txn_volume' => 10320400.50],
    ['code' => 'BY', 'name' => 'Беларусь',    'flag' => '🇧🇾', 'txn_count' =>  5820, 'txn_volume' =>  4250300.25],
    ['code' => 'TR', 'name' => 'Турция',      'flag' => '🇹🇷', 'txn_count' =>  3410, 'txn_volume' =>  5180200.75],
    ['code' => 'AE', 'name' => 'ОАЭ',         'flag' => '🇦🇪', 'txn_count' =>  2180, 'txn_volume' =>  4820100.00],
    ['code' => 'DE', 'name' => 'Германия',    'flag' => '🇩🇪', 'txn_count' =>  1840, 'txn_volume' =>  3510400.50],
    ['code' => 'US', 'name' => 'США',         'flag' => '🇺🇸', 'txn_count' =>  1202, 'txn_volume' =>  2620300.00],
];

// -- Oracle query: SELECT * FROM BAAS_MONITOR.ROLES ORDER BY role_name
$MOCK_ROLES = [
    ['role_id' => 1, 'role_name' => 'Administrator', 'description' => 'Full access — users, partners, settings'],
    ['role_id' => 2, 'role_name' => 'Manager',       'description' => 'Create/edit partners and packages'],
    ['role_id' => 3, 'role_name' => 'Analyst',       'description' => 'Read-only access to all reports'],
    ['role_id' => 4, 'role_name' => 'Viewer',        'description' => 'Dashboard and transactions view only'],
];

// -- Oracle query: SELECT u.*, r.role_name FROM BAAS_MONITOR.USERS u JOIN BAAS_MONITOR.ROLES r ON r.role_id=u.role_id
$MOCK_USERS = [
    ['user_id' => 1, 'full_name' => 'Aynur Həsənova',  'login' => 'admin',   'email' => 'a.hasanova@bank.az',  'role_id' => 1, 'role_name' => 'Administrator', 'status' => 'active',   'created_at' => '01.01.2024', 'last_login' => '12.05.2026'],
    ['user_id' => 2, 'full_name' => 'Elçin Quliyev',   'login' => 'manager', 'email' => 'e.quliyev@bank.az',   'role_id' => 2, 'role_name' => 'Manager',       'status' => 'active',   'created_at' => '15.01.2024', 'last_login' => '11.05.2026'],
    ['user_id' => 3, 'full_name' => 'Leyla Əhmədova',  'login' => 'analyst', 'email' => 'l.ahmadova@bank.az',  'role_id' => 3, 'role_name' => 'Analyst',       'status' => 'active',   'created_at' => '20.02.2024', 'last_login' => '10.05.2026'],
    ['user_id' => 4, 'full_name' => 'Orxan Babayev',   'login' => 'obabayev','email' => 'o.babayev@bank.az',   'role_id' => 4, 'role_name' => 'Viewer',        'status' => 'active',   'created_at' => '01.03.2024', 'last_login' => '08.05.2026'],
    ['user_id' => 5, 'full_name' => 'Günel Musayeva',  'login' => 'gmusayeva','email' => 'g.musayeva@bank.az', 'role_id' => 3, 'role_name' => 'Analyst',       'status' => 'inactive', 'created_at' => '10.04.2024', 'last_login' => '02.04.2026'],
];

// -- Oracle query: SELECT * FROM BAAS_MONITOR.PARTNER_DOCUMENTS WHERE partner_id = :pid ORDER BY uploaded_at DESC
$MOCK_PARTNER_DOCS = [
    1 => [
        ['doc_id' => 1, 'partner_id' => 1, 'file_name' => 'AzarTech_Muqavile_2023.pdf',     'file_type' => 'pdf',  'file_size' => 245120, 'uploaded_by' => 'Aynur Həsənova',  'uploaded_at' => '20.01.2023', 'notes' => 'İmzalanmış müqavilə'],
        ['doc_id' => 2, 'partner_id' => 1, 'file_name' => 'AzarTech_NDA_2023.docx',         'file_type' => 'docx', 'file_size' => 32768,  'uploaded_by' => 'Aynur Həsənova',  'uploaded_at' => '20.01.2023', 'notes' => 'Məxfilik sazişi'],
        ['doc_id' => 3, 'partner_id' => 1, 'file_name' => 'AzarTech_TarifElave_2024.docx',  'file_type' => 'docx', 'file_size' => 41984,  'uploaded_by' => 'Elçin Quliyev',   'uploaded_at' => '05.01.2024', 'notes' => '2024-cü il tarif əlavəsi'],
    ],
    2 => [
        ['doc_id' => 4, 'partner_id' => 2, 'file_name' => 'FinTex_Muqavile_2022.pdf',       'file_type' => 'pdf',  'file_size' => 312320, 'uploaded_by' => 'Elçin Quliyev',   'uploaded_at' => '10.11.2022', 'notes' => 'Əsas müqavilə'],
        ['doc_id' => 5, 'partner_id' => 2, 'file_name' => 'FinTex_TarifElave_2024.pdf',     'file_type' => 'pdf',  'file_size' => 128000, 'uploaded_by' => 'Elçin Quliyev',   'uploaded_at' => '15.06.2024', 'notes' => 'Tarif paketi əlavəsi'],
    ],
    3 => [
        ['doc_id' => 6, 'partner_id' => 3, 'file_name' => 'EasyShop_Muqavile_2023.pdf',     'file_type' => 'pdf',  'file_size' => 198656, 'uploaded_by' => 'Aynur Həsənova',  'uploaded_at' => '25.03.2023', 'notes' => ''],
    ],
    4 => [],
    5 => [
        ['doc_id' => 7, 'partner_id' => 5, 'file_name' => 'KreditXidmet_NDA_2022.docx',     'file_type' => 'docx', 'file_size' => 29696,  'uploaded_by' => 'Aynur Həsənova',  'uploaded_at' => '12.08.2022', 'notes' => 'NDA'],
    ],
    6 => [
        ['doc_id' => 8, 'partner_id' => 6, 'file_name' => 'ReqemsalBank_Muqavile_2024.pdf', 'file_type' => 'pdf',  'file_size' => 278528, 'uploaded_by' => 'Elçin Quliyev',   'uploaded_at' => '30.01.2024', 'notes' => 'İmzalanmış əsas müqavilə'],
        ['doc_id' => 9, 'partner_id' => 6, 'file_name' => 'ReqemsalBank_NDA_2024.pdf',      'file_type' => 'pdf',  'file_size' => 156672, 'uploaded_by' => 'Elçin Quliyev',   'uploaded_at' => '30.01.2024', 'notes' => 'Məxfilik sazişi'],
    ],
];

// Generate 30-day daily transaction data for charts
function mock_daily_txn_data(int $partner_id = 0): array {
    $base_volumes = [1 => 950000, 2 => 610000, 3 => 420000, 4 => 715000, 6 => 1190000];
    $data = [];
    for ($i = 29; $i >= 0; $i--) {
        $ts          = strtotime("-{$i} days");
        $date        = date('d.m', $ts);
        $date_full   = date('Y-m-d', $ts);
        $dow         = (int)date('N', $ts);
        $wknd        = ($dow >= 6) ? 0.63 : 1.0;
        $seed        = ($i * 137 + $partner_id * 31) % 100;
        $variation   = 1.0 + ($seed / 100 - 0.5) * 0.18;

        if ($partner_id > 0 && isset($base_volumes[$partner_id])) {
            $vol   = round($base_volumes[$partner_id] * $variation * $wknd, 2);
            $cnt   = (int)round($vol / 650);
        } else {
            $vol = $cnt = 0;
            foreach ($base_volumes as $pid => $base) {
                $sv  = ($i * 137 + $pid * 31) % 100;
                $var = 1.0 + ($sv / 100 - 0.5) * 0.18;
                $v   = round($base * $var * $wknd, 2);
                $vol += $v;
                $cnt += (int)round($v / 650);
            }
            $vol = round($vol, 2);
        }
        $data[] = ['date' => $date, 'date_full' => $date_full, 'volume' => $vol, 'count' => $cnt];
    }
    return $data;
}

// ----------------------------------------------------------------------------
// HELPERS
// ----------------------------------------------------------------------------
function fmt_num(float $n, int $dec = 0): string {
    return number_format($n, $dec, '.', ' ');
}

function fmt_rub(float $n): string {
    if ($n >= 1_000_000) return fmt_num($n / 1_000_000, 2) . ' mln ₼';
    if ($n >= 1_000)     return fmt_num($n / 1_000, 1) . ' min ₼';
    return fmt_num($n, 2) . ' ₼';
}

function status_badge(string $status): string {
    $map = [
        'active'    => ['Active / Активен',    'badge-active'],
        'inactive'  => ['Inactive / Неактивен','badge-inactive'],
        'exhausted' => ['Exhausted / Исчерпан','badge-exhausted'],
        'closed'    => ['Closed / Закрыт',     'badge-closed'],
        'suspended' => ['Suspended',            'badge-inactive'],
    ];
    [$label, $cls] = $map[$status] ?? [$status, 'badge-inactive'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

function usage_bar(float $pct): string {
    $color = $pct >= 90 ? 'bar-danger' : ($pct >= 70 ? 'bar-warning' : 'bar-ok');
    $w     = min(100, $pct);
    return "<div class=\"usage-bar-wrap\">
              <div class=\"usage-bar {$color}\" style=\"width:{$w}%\"></div>
            </div>
            <small class=\"usage-label\">{$pct}%</small>";
}

// ----------------------------------------------------------------------------
// LAYOUT RENDER
// ----------------------------------------------------------------------------
function render_header(string $title = 'Dashboard', string $active = 'dashboard'): void {
    $u = current_user();
    $nav = [
        ['id' => 'dashboard',     'href' => 'dashboard.php',    'icon' => 'fa-gauge-high',   'label' => 'Dashboard'],
        ['id' => 'partners',      'href' => 'partners.php',     'icon' => 'fa-handshake',    'label' => 'Partners & Packages'],
        ['id' => 'transactions',  'href' => 'transactions.php', 'icon' => 'fa-chart-line',   'label' => 'Transactions'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
/* ===== RESET & BASE ===== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;font-size:14px;background:#f0f4f8;color:#1a2332;display:flex;min-height:100vh}

/* ===== SIDEBAR ===== */
.sidebar{width:240px;min-height:100vh;background:#0f1e3c;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-brand .brand-name{color:#fff;font-size:16px;font-weight:700;letter-spacing:.5px}
.sidebar-brand .brand-sub{color:#6b8aad;font-size:11px;margin-top:2px}
.sidebar-brand .brand-icon{width:36px;height:36px;background:linear-gradient(135deg,#1e88e5,#0d47a1);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.sidebar-brand .brand-icon i{color:#fff;font-size:16px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;color:#8facc8;text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;cursor:pointer}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-item.active{background:rgba(30,136,229,.18);color:#4db3ff;border-right:3px solid #1e88e5}
.nav-item i{width:18px;text-align:center;font-size:14px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08)}
.user-info{display:flex;align-items:center;gap:10px}
.user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1e88e5,#0d47a1);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.user-name{color:#c8d8ea;font-size:12px;font-weight:600}
.user-role{color:#6b8aad;font-size:11px}
.logout-btn{display:block;margin-top:10px;padding:7px 12px;background:rgba(255,255,255,.06);color:#8facc8;text-decoration:none;border-radius:6px;font-size:12px;text-align:center;transition:all .18s}
.logout-btn:hover{background:rgba(229,57,53,.2);color:#ef9a9a}

/* ===== MAIN CONTENT ===== */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-title{font-size:18px;font-weight:700;color:#1a2332}
.topbar-meta{display:flex;align-items:center;gap:14px}
.sync-badge{background:#e8f5e9;color:#2e7d32;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.sync-badge.warn{background:#fff3e0;color:#e65100}
.page-content{padding:24px 28px;flex:1}

/* ===== CARDS ===== */
.cards-row{display:grid;gap:16px;margin-bottom:22px}
.cards-row-6{grid-template-columns:repeat(6,1fr)}
.cards-row-3{grid-template-columns:repeat(3,1fr)}
.cards-row-2{grid-template-columns:repeat(2,1fr)}
@media(max-width:1400px){.cards-row-6{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.cards-row-6,.cards-row-3{grid-template-columns:repeat(2,1fr)}.cards-row-2{grid-template-columns:1fr}}

.kpi-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid #1e88e5;display:flex;flex-direction:column;gap:6px}
.kpi-card.green{border-color:#43a047}
.kpi-card.orange{border-color:#f57c00}
.kpi-card.red{border-color:#e53935}
.kpi-card.purple{border-color:#8e24aa}
.kpi-card.teal{border-color:#00897b}
.kpi-label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.kpi-value{font-size:26px;font-weight:700;color:#1a2332;line-height:1}
.kpi-sub{font-size:11px;color:#94a3b8}

.chart-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.07)}
.chart-card-title{font-size:13px;font-weight:700;color:#334155;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.chart-card-title i{color:#1e88e5}

/* ===== TABLES ===== */
.table-card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;margin-bottom:22px}
.table-card-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.table-card-header h3{font-size:14px;font-weight:700;color:#334155}
table{width:100%;border-collapse:collapse}
thead th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .12s}
tbody tr:hover{background:#f8fafc}
tbody tr:last-child{border-bottom:none}
td{padding:12px 16px;font-size:13px;color:#334155;vertical-align:middle}
td.mono{font-family:monospace;font-size:12px}
td.right{text-align:right}
td.center{text-align:center}

/* ===== BADGES ===== */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
.badge-active{background:#e8f5e9;color:#2e7d32}
.badge-inactive{background:#fce4ec;color:#c62828}
.badge-exhausted{background:#fff3e0;color:#e65100}
.badge-closed{background:#f3e5f5;color:#6a1b9a}

/* ===== USAGE BAR ===== */
.usage-bar-wrap{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;width:100%;min-width:80px}
.usage-bar{height:100%;border-radius:4px;transition:width .4s}
.bar-ok{background:linear-gradient(90deg,#43a047,#66bb6a)}
.bar-warning{background:linear-gradient(90deg,#f57c00,#ffb74d)}
.bar-danger{background:linear-gradient(90deg,#e53935,#ef5350)}
.usage-label{color:#64748b;font-size:11px;margin-top:3px;display:block}

/* ===== FILTER BAR ===== */
.filter-bar{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:5px}
.filter-group label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.filter-group select,.filter-group input{border:1px solid #e2e8f0;border-radius:7px;padding:7px 11px;font-size:13px;color:#334155;background:#fff;outline:none;font-family:inherit;min-width:150px}
.filter-group select:focus,.filter-group input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12)}
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .18s;text-decoration:none}
.btn-primary{background:#1e88e5;color:#fff}
.btn-primary:hover{background:#1565c0}
.btn-outline{background:#fff;color:#334155;border:1px solid #e2e8f0}
.btn-outline:hover{background:#f8fafc}

/* ===== MODAL ===== */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:14px;width:780px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-header{padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
.modal-header h2{font-size:16px;font-weight:700}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;padding:4px}
.modal-body{padding:24px}

/* ===== SECTION LABEL ===== */
.section-label{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;margin-top:20px}
.section-label:first-child{margin-top:0}

/* ===== ALERT / NOTICE ===== */
.notice{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
.notice.info{background:#e3f2fd;color:#0d47a1}
.notice.warn{background:#fff3e0;color:#bf360c}
.notice i{margin-top:1px}
</style>
<?php
}  // end render_header

function render_nav(string $active): void {
    $nav = [
        ['id' => 'dashboard',    'href' => 'dashboard.php',    'icon' => 'fa-gauge-high',  'label' => 'Dashboard'],
        ['id' => 'partners',     'href' => 'partners.php',     'icon' => 'fa-handshake',   'label' => 'Partners & Packages'],
        ['id' => 'transactions', 'href' => 'transactions.php', 'icon' => 'fa-chart-line',  'label' => 'Transactions'],
    ];
    if (is_admin()) {
        $nav[] = ['id' => 'users', 'href' => 'users.php', 'icon' => 'fa-users-gear', 'label' => 'Users & Roles'];
    }
    $u = current_user();
    $initials = implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w,0,1)), array_slice(explode(' ', $u['name'] ?? 'Admin'), 0, 2)));
    ?>
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-building-columns"></i></div>
    <div class="brand-name"><?= APP_NAME ?></div>
    <div class="brand-sub">BaaS Partner Monitoring</div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-item <?= $active === $item['id'] ? 'active' : '' ?>">
      <i class="fa-solid <?= $item['icon'] ?>"></i>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($u['name'] ?? 'Admin') ?></div>
        <div class="user-role"><?= htmlspecialchars($u['role'] ?? 'Administrator') ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
  </div>
</div>
<?php
}

function render_topbar(string $title): void { ?>
<div class="topbar">
  <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
  <div class="topbar-meta">
    <span class="sync-badge"><i class="fa-solid fa-rotate"></i> Last sync: 07.05.2026 02:09</span>
    <span style="color:#94a3b8;font-size:12px">Oracle DWH: <strong style="color:#2e7d32">Connected</strong></span>
  </div>
</div>
<?php }

function render_footer(): void { ?>
</div><!-- .main -->
</body>
</html>
<?php }
