# BaaS Monitor — PHP + Supabase

Server-rendered PHP application that monitors BaaS partners, card packages, transactions, MCC categories, country breakdowns, users and roles. Data lives in Supabase (PostgreSQL + Storage). The previous HTML single-page version and the Oracle code paths have been removed — this is a single, deployable PHP project.

## Requirements

- PHP 8.0 or newer with the `curl` extension enabled.
- Any web server that runs PHP (Apache + mod_php, nginx + PHP-FPM, Caddy, Render's PHP runtime, Fly.io, shared hosting, etc.).
- A Supabase project with the tables created by the SQL files in `sql/`.

No PHP package manager is needed. No `composer install`. Just upload the files and point a web root at this folder.

## What's in this folder

| File | Purpose |
|---|---|
| `index.php` | Login page (Supabase-only auth, bcrypt). |
| `dashboard.php` | KPI dashboard. |
| `partners.php` | Partners & packages list, partner detail modal. |
| `transactions.php` | Transaction analytics, currency filter. |
| `users.php` | Users & roles (admin only). |
| `logout.php` | Session destroyer. |
| `generate_doc.php` | DOCX generator for contract / tariff / NDA. |
| `_common.php` | Shared layout, auth helpers, data loader. |
| `_supabase.php` | Supabase REST + Storage helper. |
| `_partners_mid.php` | Partner detail / edit modal partials. |
| `config.php` | All configuration. **Edit before deploying.** |
| `sql/supabase_setup.sql` | Base schema (partners, packages, sys_users, partner_docs). |
| `sql/supabase_migration_v2.sql` | Field additions. |
| `sql/supabase_migration_v3.sql` | `password` column, `mcc_data`, `country_data`. |
| `sql/supabase_migration_v4.sql` | `fee_packages`, bcrypt password hashes, RLS lockdown. |

## What changed from the previous build

- The single-page HTML (`baas-monitor.html`) is removed. The PHP pages render everything server-side.
- The Oracle PDO code path is removed. The `mock` data path is also removed — the `$MOCK_*` arrays in `_common.php` are now empty containers populated from Supabase at request time. (The variable names are kept because the page files reference them; only the source of the data changed.)
- `config.php` lost its `db_monitor`, `db_dwh`, `tables`, `sync`, and `mode` sections. The Supabase section stayed and the key now expects the **service-role secret**, not the anon key.
- `_supabase.php` gained `sb_load_fee_packages()` + a `fee_package_from_sb()` mapper, plus three Storage helpers: `sb_upload_file()`, `sb_storage_public_url()`, `sb_signed_url()`. `sb_verify_login()` was rewritten to use bcrypt via `password_verify()` instead of comparing plaintext passwords through the REST API.
- `index.php` lost the hardcoded `$DEMO_USERS` fallback and the on-screen demo credentials block. Login goes through Supabase or it fails.
- `mock_daily_txn_data()` is kept as a stub that returns an empty 30-day series. Charts on dashboard and transactions will show zeros until you wire a real transactions table.

## First-time deploy

1. **Create the Supabase project.** Pick a region and note the project URL.

2. **Apply the SQL files in order** in the Supabase SQL editor:
   1. `sql/supabase_setup.sql`
   2. `sql/supabase_migration_v2.sql`
   3. `sql/supabase_migration_v3.sql`
   4. `sql/supabase_migration_v4.sql` — **only after you complete step 3 below**, otherwise the app will instantly lose access.

3. **Get the service-role secret.** Supabase Dashboard → Project Settings → API → copy the `service_role` secret (it's labelled secret for a reason — it bypasses RLS). Paste it into `config.php` at `supabase.service_role_key`, replacing `PASTE_SERVICE_ROLE_KEY_HERE`. Also confirm `supabase.url` matches your project.

4. **Create the Storage bucket.** Supabase Dashboard → Storage → New bucket → name `partner-docs` (or change the name in `config.php` → `supabase.storage_bucket`). Mark it private. The PHP code uses signed URLs for downloads (`sb_signed_url`) — public-bucket mode is available via `sb_storage_public_url` if you'd rather.

5. **Run migration v4.** This creates `fee_packages`, hashes existing seeded passwords with bcrypt, and drops the wide-open `allow_all` RLS policies. After this point the anon key cannot read anything — the PHP server uses the service-role key, which is fine.

6. **Upload the folder to a PHP host.** The web root should point at the folder containing `index.php`. Block direct HTTP access to includes by adding a webserver rule (Apache example below) or by moving `config.php`, `_common.php`, `_supabase.php`, `_partners_mid.php` above the document root and adjusting `require_once` paths.

   Apache `.htaccess` example for the folder:

   ```
   <FilesMatch "^(_common|_supabase|_partners_mid|config)\.php$">
     Require all denied
   </FilesMatch>
   ```

7. **Log in.** The demo logins from migration v3 (`admin / admin123`, `analyst / analyst123`, `manager / manager123`, `manager2 / manager123`, `viewer1 / viewer123`) now work with bcrypt-hashed passwords. **Change them immediately** in the Users page (admin) before doing anything real with this deployment.

## Day-to-day notes

- All data is fetched on every request. There is no app-level cache yet despite `cache.enabled = true` in config — that's a future hook.
- If Supabase is unreachable, pages render empty states rather than crashing. The PHP error log will contain `[BaaS] …` lines.
- Document file uploads go through `sb_upload_file()` into the `partner-docs` bucket. The path pattern is `partner-{id}/{timestamp}_{sanitized_filename}`.
- `mock_daily_txn_data()` returns zeros across 30 days. When you have a real transactions table on Supabase, replace the body of that function in `_common.php` (or add a new `sb_load_daily_txn()` and call it from `dashboard.php` and `transactions.php`).
- `fee_packages` has a loader but none of the current PHP pages render it. The table is in place for future use.
- The page files (`dashboard.php`, `partners.php`, `transactions.php`, `users.php`) still contain commented Oracle SQL and `else { … PDO …}` branches as documentation. Those branches are unreachable now (`USE_MOCK_DATA` is hardcoded to `true`) — they're left in place because the inline SQL comments are a useful spec if you ever port back to a relational DB. They can be safely deleted whenever.

## Security checklist before going live

- [ ] `supabase.service_role_key` set to the real secret in `config.php`.
- [ ] Migration v4 has been applied (`fee_packages` exists, sys_users.password values start with `$2b$`, `pg_policies` shows no `allow_all` rows for the listed tables).
- [ ] Demo passwords changed.
- [ ] `config.php` (and the underscore-prefixed includes) not directly accessible via HTTP.
- [ ] HTTPS in front of PHP — the service-role key is in the response of every page request to your backend, so the channel must be TLS.
- [ ] Backups configured in Supabase.
- [ ] Storage bucket policy reviewed (private + signed URLs is the safer default).
