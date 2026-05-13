<?php
// =============================================================================
// _supabase.php — Supabase REST API helper for PHP
// BaaS Partner Monitoring System
//
// Подключается к Supabase через REST API (cURL).
// Все функции используют глобальный $cfg['supabase'] для URL и ключа.
// Маппинг полей: Supabase-колонки → формат, ожидаемый PHP-шаблонами.
// =============================================================================

/**
 * Базовый HTTP-запрос к Supabase REST API.
 *
 * @param string      $method  GET | POST | PATCH | DELETE
 * @param string      $table   Название таблицы (напр. 'partners')
 * @param array|null  $body    Тело запроса (для POST/PATCH)
 * @param string      $query   Query-параметры (напр. '?order=id&status=eq.active')
 * @return array Распарсенный JSON-ответ
 * @throws RuntimeException при HTTP-ошибке или недоступности Supabase
 */
function sb_request(string $method, string $table, ?array $body = null, string $query = ''): array {
    global $cfg;

    $base = rtrim($cfg['supabase']['url'] ?? '', '/');
    $key  = $cfg['supabase']['anon_key'] ?? '';

    $url = $base . '/rest/v1/' . $table . $query;

    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if (in_array($method, ['POST', 'PATCH'], true)) {
        $headers[] = 'Prefer: return=representation';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Supabase cURL error: ' . $curl_err);
    }
    if ($http_code >= 400) {
        throw new RuntimeException("Supabase HTTP {$http_code} on {$method} {$table}: " . $response);
    }

    if ($response === '' || $response === null) {
        return [];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

// ─────────────────────────────────────────────────────────────────────────────
// FIELD MAPPERS — Supabase column names → PHP template field names
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Supabase `partners` row → формат $MOCK_PARTNERS
 */
function partner_from_sb(array $r): array {
    return [
        'partner_id'         => (int)($r['id'] ?? 0),
        'partner_name'       => $r['name']           ?? '',
        'legal_form'         => $r['form']           ?? 'MMC',
        'status'             => $r['status']         ?? 'active',
        'contract_date'      => $r['date']           ?? '',
        'contact_email'      => $r['email']          ?? '',
        'contact_phone'      => $r['phone']          ?? '',
        'account_manager'    => $r['mgr']            ?? '',
        'voen'               => $r['voen']           ?? '',
        'signatory_name'     => $r['signatory']      ?? '',
        'signatory_position' => $r['signatory_pos']  ?? '',
        'bank_name'          => $r['bank_name']      ?? '',
        'bank_account'       => $r['bank_account']   ?? '',
        'bank_code'          => $r['bank_code']      ?? '',
        'legal_address'      => $r['legal_address']  ?? '',
    ];
}

/**
 * Supabase `packages` row → формат $MOCK_PACKAGES
 */
function package_from_sb(array $r): array {
    return [
        'package_id'      => (int)($r['id']          ?? 0),
        'partner_id'      => (int)($r['partner_id']  ?? 0),
        'package_size'    => (int)($r['size']         ?? 0),
        'start_date'      => $r['start_date']         ?? '',
        'end_date'        => $r['end_date']           ?? '',
        'status'          => $r['status']             ?? 'active',
        'issued_cards'    => (int)($r['issued']       ?? 0),
        'remaining_cards' => (int)($r['rem']          ?? 0),
        'usage_percent'   => (float)($r['pct']        ?? 0),
        'notes'           => $r['notes']              ?? '',
    ];
}

/**
 * Supabase `sys_users` row → формат $MOCK_USERS
 */
function user_from_sb(array $r): array {
    $role = $r['role'] ?? 'viewer';
    return [
        'user_id'    => (int)($r['id']     ?? 0),
        'full_name'  => $r['name']         ?? '',
        'login'      => $r['login']        ?? '',
        'email'      => $r['email']        ?? '',
        'role_id'    => 0,                         // нет отдельной таблицы ролей в Supabase
        'role_name'  => ucfirst($role),
        'status'     => $r['status']       ?? 'active',
        'created_at' => isset($r['created_at'])
                         ? date('d.m.Y', strtotime($r['created_at']))
                         : '',
        'last_login' => '',
    ];
}

/**
 * Supabase `partner_docs` row → формат $MOCK_PARTNER_DOCS[pid][*]
 */
function doc_from_sb(array $r): array {
    $filename  = $r['name'] ?? '';
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return [
        'doc_id'       => (int)($r['id']         ?? 0),
        'partner_id'   => (int)($r['partner_id'] ?? 0),
        'file_name'    => $filename,
        'file_type'    => $extension,
        'file_size'    => (int)($r['size']        ?? 0),
        'uploaded_by'  => '',
        'uploaded_at'  => $r['date']              ?? '',
        'notes'        => $r['note']              ?? '',
        'storage_path' => $r['storage_path']      ?? '',
    ];
}

/**
 * Supabase `mcc_data` row → формат $MOCK_MCC
 */
function mcc_from_sb(array $r): array {
    return [
        'mcc'        => $r['mcc']        ?? '',
        'label'      => $r['label']      ?? '',
        'txn_count'  => (int)($r['txn_count']  ?? 0),
        'txn_volume' => (float)($r['txn_volume'] ?? 0),
    ];
}

/**
 * Supabase `country_data` row → формат $MOCK_COUNTRY
 */
function country_from_sb(array $r): array {
    return [
        'code'       => $r['code']       ?? '',
        'name'       => $r['name']       ?? '',
        'flag'       => $r['flag']       ?? '',
        'txn_count'  => (int)($r['txn_count']  ?? 0),
        'txn_volume' => (float)($r['txn_volume'] ?? 0),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// DATA LOADING FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────

function sb_load_partners(): array {
    $rows = sb_request('GET', 'partners', null, '?order=name.asc');
    return array_map('partner_from_sb', $rows);
}

function sb_load_packages(): array {
    $rows = sb_request('GET', 'packages', null, '?order=id.asc');
    return array_map('package_from_sb', $rows);
}

function sb_load_users(): array {
    $rows = sb_request('GET', 'sys_users', null, '?order=name.asc');
    return array_map('user_from_sb', $rows);
}

function sb_load_docs(): array {
    $rows = sb_request('GET', 'partner_docs', null, '?order=id.asc');
    return array_map('doc_from_sb', $rows);
}

function sb_load_mcc(): array {
    $rows = sb_request('GET', 'mcc_data', null, '?order=txn_volume.desc');
    return array_map('mcc_from_sb', $rows);
}

function sb_load_country(): array {
    $rows = sb_request('GET', 'country_data', null, '?order=txn_volume.desc');
    return array_map('country_from_sb', $rows);
}

/**
 * Проверка логина и пароля через sys_users.
 * Возвращает строку пользователя или null если не найден.
 */
function sb_verify_login(string $login, string $password): ?array {
    $query = '?login=eq.' . urlencode($login)
           . '&password=eq.' . urlencode($password)
           . '&status=eq.active'
           . '&limit=1';
    $rows = sb_request('GET', 'sys_users', null, $query);
    return $rows[0] ?? null;
}

/**
 * Вычислить $MOCK_PARTNER_SUMMARY из массивов партнёров и пакетов.
 * Транзакционные объёмы остаются 0 — в Supabase нет таблицы транзакций.
 */
function sb_build_partner_summary(array $partners, array $packages): array {
    $summary = [];
    foreach ($partners as $p) {
        $pid  = $p['partner_id'];
        $pkgs = array_filter($packages, fn($pk) => $pk['partner_id'] === $pid);
        $pkgs = array_values($pkgs);

        $total_size      = array_sum(array_column($pkgs, 'package_size'));
        $total_issued    = array_sum(array_column($pkgs, 'issued_cards'));
        $total_remaining = array_sum(array_column($pkgs, 'remaining_cards'));
        $avg_pct         = $total_size > 0
                           ? round($total_issued / $total_size * 100, 2)
                           : 0.0;

        $summary[$pid] = [
            'total_pkg_size'  => $total_size,
            'total_issued'    => $total_issued,
            'total_remaining' => $total_remaining,
            'avg_usage_pct'   => $avg_pct,
            'txn_volume_30d'  => 0.0,   // транзакции не хранятся в Supabase
            'txn_count_30d'   => 0,
        ];
    }
    return $summary;
}

/**
 * Сгруппировать документы по partner_id: array<int, array>
 */
function sb_build_partner_docs(array $partners, array $all_docs): array {
    $result = [];
    foreach ($partners as $p) {
        $result[$p['partner_id']] = [];
    }
    foreach ($all_docs as $doc) {
        $pid = (int)$doc['partner_id'];
        if (!isset($result[$pid])) {
            $result[$pid] = [];
        }
        $result[$pid][] = $doc;
    }
    return $result;
}
