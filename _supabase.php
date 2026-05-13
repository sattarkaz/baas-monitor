<?php
// =============================================================================
// _supabase.php — Supabase REST + Storage helper for PHP
// BaaS Partner Monitoring System
//
// All functions read $cfg['supabase'] from config.php. The API key MUST be
// the service_role secret in production. The anon key works but only with
// permissive RLS — see README.
//
// Tables expected:
//   partners, packages, sys_users, partner_docs, mcc_data, country_data,
//   fee_packages
// Storage bucket:
//   partner-docs (configurable in config.php → supabase.storage_bucket)
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// LOW-LEVEL HTTP HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Base REST request to Supabase PostgREST.
 *
 * @param string     $method GET | POST | PATCH | DELETE
 * @param string     $table  Table name (e.g. 'partners')
 * @param array|null $body   Request body for POST/PATCH
 * @param string     $query  PostgREST query string, including the leading '?'
 * @return array Decoded JSON response
 * @throws RuntimeException on transport or HTTP error
 */
function sb_request(string $method, string $table, ?array $body = null, string $query = ''): array {
    global $cfg;

    $base = rtrim($cfg['supabase']['url'] ?? '', '/');
    $key  = $cfg['supabase']['service_role_key'] ?? '';

    if ($base === '' || $key === '' || $key === 'PASTE_SERVICE_ROLE_KEY_HERE') {
        throw new RuntimeException('Supabase is not configured. Edit config.php (supabase.url + supabase.service_role_key).');
    }

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
        CURLOPT_TIMEOUT        => $cfg['supabase']['request_timeout'] ?? 10,
        CURLOPT_CONNECTTIMEOUT => $cfg['supabase']['connect_timeout'] ?? 5,
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

/**
 * Upload raw bytes to Supabase Storage.
 *
 * @param string $path_in_bucket  e.g. 'partner-1/1715600000_contract.pdf'
 * @param string $bytes           Raw file contents
 * @param string $content_type    MIME type
 * @return string The stored path (echoed for convenience)
 * @throws RuntimeException
 */
function sb_upload_file(string $path_in_bucket, string $bytes, string $content_type = 'application/octet-stream'): string {
    global $cfg;

    $base   = rtrim($cfg['supabase']['url'] ?? '', '/');
    $key    = $cfg['supabase']['service_role_key'] ?? '';
    $bucket = $cfg['supabase']['storage_bucket'] ?? 'partner-docs';

    if ($base === '' || $key === '' || $key === 'PASTE_SERVICE_ROLE_KEY_HERE') {
        throw new RuntimeException('Supabase is not configured.');
    }

    $url = $base . '/storage/v1/object/' . $bucket . '/' . ltrim($path_in_bucket, '/');

    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: ' . $content_type,
        'x-upsert: true',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $bytes,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Supabase Storage upload error: ' . $curl_err);
    }
    if ($http_code >= 400) {
        throw new RuntimeException("Supabase Storage HTTP {$http_code}: " . $response);
    }

    return $path_in_bucket;
}

/**
 * Build a public-style URL for a stored object.
 * For private buckets, use sb_signed_url() instead.
 */
function sb_storage_public_url(string $path_in_bucket): string {
    global $cfg;
    $base   = rtrim($cfg['supabase']['url'] ?? '', '/');
    $bucket = $cfg['supabase']['storage_bucket'] ?? 'partner-docs';
    return $base . '/storage/v1/object/public/' . $bucket . '/' . ltrim($path_in_bucket, '/');
}

/**
 * Generate a signed URL (private bucket).
 * @throws RuntimeException
 */
function sb_signed_url(string $path_in_bucket, int $expires_in_sec = 300): string {
    global $cfg;
    $base   = rtrim($cfg['supabase']['url'] ?? '', '/');
    $key    = $cfg['supabase']['service_role_key'] ?? '';
    $bucket = $cfg['supabase']['storage_bucket'] ?? 'partner-docs';

    $url = $base . '/storage/v1/object/sign/' . $bucket . '/' . ltrim($path_in_bucket, '/');
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode(['expiresIn' => $expires_in_sec]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code >= 400) {
        throw new RuntimeException("Supabase signed URL HTTP {$http_code}: " . $response);
    }
    $decoded = json_decode($response ?: '', true);
    $signed  = $decoded['signedURL'] ?? $decoded['signedUrl'] ?? '';
    if ($signed === '') {
        throw new RuntimeException('Supabase signed URL: empty response');
    }
    return $base . '/storage/v1' . $signed;
}

// ─────────────────────────────────────────────────────────────────────────────
// FIELD MAPPERS — Supabase row → array shape consumed by page templates
// ─────────────────────────────────────────────────────────────────────────────

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

function package_from_sb(array $r): array {
    return [
        'package_id'      => (int)($r['id']          ?? 0),
        'partner_id'      => (int)($r['partner_id']  ?? 0),
        'package_size'    => (int)($r['size']        ?? 0),
        'start_date'      => $r['start_date']        ?? '',
        'end_date'        => $r['end_date']          ?? '',
        'status'          => $r['status']            ?? 'active',
        'issued_cards'    => (int)($r['issued']      ?? 0),
        'remaining_cards' => (int)($r['rem']         ?? 0),
        'usage_percent'   => (float)($r['pct']       ?? 0),
        'notes'           => $r['notes']             ?? '',
    ];
}

function user_from_sb(array $r): array {
    $role = $r['role'] ?? 'viewer';
    return [
        'user_id'    => (int)($r['id']     ?? 0),
        'full_name'  => $r['name']         ?? '',
        'login'      => $r['login']        ?? '',
        'email'      => $r['email']        ?? '',
        'role_id'    => 0,
        'role_name'  => ucfirst($role),
        'status'     => $r['status']       ?? 'active',
        'created_at' => isset($r['created_at'])
                         ? date('d.m.Y', strtotime($r['created_at']))
                         : '',
        'last_login' => '',
    ];
}

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

function mcc_from_sb(array $r): array {
    return [
        'mcc'        => $r['mcc']        ?? '',
        'label'      => $r['label']      ?? '',
        'txn_count'  => (int)($r['txn_count']  ?? 0),
        'txn_volume' => (float)($r['txn_volume'] ?? 0),
    ];
}

function country_from_sb(array $r): array {
    return [
        'code'       => $r['code']       ?? '',
        'name'       => $r['name']       ?? '',
        'flag'       => $r['flag']       ?? '',
        'txn_count'  => (int)($r['txn_count']  ?? 0),
        'txn_volume' => (float)($r['txn_volume'] ?? 0),
    ];
}

function fee_package_from_sb(array $r): array {
    // 'tiers' is jsonb in the DB and arrives as a parsed array.
    $tiers = $r['tiers'] ?? [];
    if (is_string($tiers)) {
        $decoded = json_decode($tiers, true);
        $tiers   = is_array($decoded) ? $decoded : [];
    }
    return [
        'fee_id'     => (int)($r['id']         ?? 0),
        'partner_id' => (int)($r['partner_id'] ?? 0),
        'name'       => $r['name']             ?? '',
        'start_date' => $r['start_date']       ?? '',
        'end_date'   => $r['end_date']         ?? '',
        'status'     => $r['status']           ?? 'active',
        'tiers'      => $tiers,
        'notes'      => $r['notes']            ?? '',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// DATA LOADING
// ─────────────────────────────────────────────────────────────────────────────

function sb_load_partners(): array {
    return array_map('partner_from_sb', sb_request('GET', 'partners', null, '?order=name.asc'));
}

function sb_load_packages(): array {
    return array_map('package_from_sb', sb_request('GET', 'packages', null, '?order=id.asc'));
}

function sb_load_users(): array {
    return array_map('user_from_sb', sb_request('GET', 'sys_users', null, '?order=name.asc'));
}

function sb_load_docs(): array {
    return array_map('doc_from_sb', sb_request('GET', 'partner_docs', null, '?order=id.asc'));
}

function sb_load_mcc(): array {
    return array_map('mcc_from_sb', sb_request('GET', 'mcc_data', null, '?order=txn_volume.desc'));
}

function sb_load_country(): array {
    return array_map('country_from_sb', sb_request('GET', 'country_data', null, '?order=txn_volume.desc'));
}

function sb_load_fee_packages(): array {
    return array_map('fee_package_from_sb', sb_request('GET', 'fee_packages', null, '?order=id.asc'));
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTHENTICATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verify a login + password against sys_users using PHP bcrypt.
 * Returns the user row on success, null on failure.
 *
 * IMPORTANT: sys_users.password MUST contain bcrypt hashes (run
 * supabase_migration_v4.sql to migrate seeded plaintext to bcrypt).
 */
function sb_verify_login(string $login, string $password): ?array {
    $query = '?login=eq.' . urlencode($login)
           . '&status=eq.active'
           . '&limit=1';
    $rows = sb_request('GET', 'sys_users', null, $query);
    $user = $rows[0] ?? null;
    if (!$user) {
        return null;
    }
    $hash = $user['password'] ?? '';
    if ($hash === '' || !password_verify($password, $hash)) {
        return null;
    }
    // Don't ship the hash back to the caller
    unset($user['password']);
    return $user;
}

// ─────────────────────────────────────────────────────────────────────────────
// DERIVED VIEWS — turn flat tables into the shapes the page files expect
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build partner_summary keyed by partner_id from partners + packages.
 * Transaction columns stay zero until a transactions table is wired up.
 */
function sb_build_partner_summary(array $partners, array $packages): array {
    $summary = [];
    foreach ($partners as $p) {
        $pid  = $p['partner_id'];
        $pkgs = array_values(array_filter($packages, fn($pk) => $pk['partner_id'] === $pid));

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
            'txn_volume_30d'  => 0.0,
            'txn_count_30d'   => 0,
        ];
    }
    return $summary;
}

/**
 * Group documents by partner_id: array<int, array>.
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
