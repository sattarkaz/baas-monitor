# BaaS Monitor — Static HTML on GitHub Pages, DWH-fed

A single-page web app that monitors BaaS partners, card packages, transactions, MCC and country breakdowns, users and roles. It runs entirely in the browser and talks directly to a Supabase project that the bank's DWH feeds with daily CSV dumps.

## What you get

```
index.html                                       ← the whole app, one file
.gitignore
README.md                                        ← this file
sql/supabase_setup.sql                           ← base schema
sql/supabase_migration_v2.sql                    ← schema additions
sql/supabase_migration_v3.sql                    ← password column, mcc_data, country_data
sql/supabase_migration_v4.sql                    ← (server-mode lockdown — DO NOT RUN for HTML mode)
sql/supabase_migration_v5_html_mode.sql          ← re-opens RLS for browser-direct access
sql/supabase_migration_v6_dwh_schema.sql         ← cards + transactions + prod_types + label tables
```

## One-time Supabase setup

If you've already done v2 → v5 from prior turns, **only run v6 now.** It adds three new tables (`cards`, `transactions`, `prod_types`), two label tables (`mcc_labels`, `country_labels` with 3-letter ISO codes), and replaces `mcc_data` and `country_data` with views computed from `transactions`. The migration is idempotent (`DROP IF EXISTS` everywhere), so re-running is safe.

After v6 has been applied, **populate `prod_types`** so the UI can join DWH cards to partners. Example:

```sql
INSERT INTO prod_types (code, description, partner_id) VALUES
  ('BAASVCGREENA', 'Visa Classic GreenPay AZN', 1),  -- GreenPay partner id
  ('BAASVCEPULA',  'Visa Classic EPUL AZN',     2);  -- EPUL partner id
-- one row per PROD_TYPE code in your DWH; many codes can point to one partner.
```

Until this lookup is populated, cards and transactions still load into the UI but won't be attributed to a partner — the partner summary will fall back to whatever `packages.issued/rem/pct` say.

## How the DWH sync should work

The HTML doesn't do ingestion — it just reads from Supabase. The bank's DWH job is responsible for:

1. **Upserting cards** into `cards` keyed on `card_id`. Drop rows with malformed dates rather than insert them. The DWH is the source of truth — every nightly run can replace columns from the CSV.
2. **Upserting transactions** into `transactions` keyed on `tx_id`. Idempotent on re-run because `tx_id` is the primary key.
3. **Recomputing package usage** after the dump is loaded. A simple `UPDATE packages` is included in the comments of `supabase_migration_v6_dwh_schema.sql` — copy it into your sync job.

The HTML refreshes everything on each login / page load via `loadData()`, so as long as the DWH lands new rows, the UI shows them on the next visit.

## Putting it on GitHub Pages

1. Sign in to github.com → **+** → **New repository**. Name it (e.g. `baas-monitor`), choose **Private**, don't tick "Add a README".
2. On the empty repo page, click **uploading an existing file**.
3. Open the project folder on your computer. Enable hidden files (Mac: Cmd+Shift+. / Windows: File Explorer → View → Show → Hidden items) so `.gitignore` is visible. Select all files and the `sql/` folder (but NOT the wrapper folder itself), drag into the upload box.
4. Commit message → `Initial commit` → Commit changes.
5. **Settings → Pages → Source: Deploy from a branch → Branch: main / root → Save.** Within a minute the URL `https://<your-username>.github.io/baas-monitor/` goes live.

To update later: edit `index.html`, commit, GitHub Pages republishes within a minute.

## Logging in

After migration v5 was applied, these demo logins work:

| Login    | Password   | Role          |
|----------|------------|---------------|
| admin    | admin123   | Administrator |
| analyst  | analyst123 | Analyst       |
| manager  | manager123 | Manager       |
| manager2 | manager123 | Manager       |
| viewer1  | viewer123  | Viewer        |

**Change these immediately** through the Users page once you log in as admin.

## How the data flow works

`index.html` has a small Supabase client at the top of its `<script>` block. On page load, `loadData()` fetches in parallel:

- `partners`, `packages`, `sys_users`, `partner_docs`, `fee_packages` — bank-managed metadata, edited through the UI.
- `cards` — full snapshot from DWH.
- `transactions` — last **90 days** only (configurable in `loadData()`). Older history stays in DWH/cold storage; surface it later via a "load more" or a separate report if needed.
- `prod_types` — code → partner mapping.
- `mcc_data`, `country_data` — these are now **views**, computed live from `transactions` joined to the label tables.

`buildSummary()` joins everything: for each partner, it sums `packages.size` for capacity, counts `cards` where `PROD_TYPE_MAP[card.prod_type] === partner.id` for issued, and sums `transactions` from the last 30 days for volume. `genDailyData()` aggregates transactions into 30-day buckets for the dashboard line chart.

## Security posture

Same trade-off as v5: the anon Supabase key is in the HTML, and migration v5's open `allow_all` policies are still in effect on every table including the new ones. Fine for a small internal bank tool on a private repo; not fine for anything public. If the audience widens later, you need the PHP version (migration v4 + service-role key) in front of Supabase.

## Local preview

Just double-click `index.html` to open it in a browser. It will talk to your live Supabase project as soon as you log in.

## Known limitations / next steps

- No UI yet for editing the `prod_types` mapping — populate it via SQL editor for now.
- No CSV-ingestion job included. Wire that up on the DWH side (cron + curl to Supabase REST, an Edge Function, or Postgres FDW).
- The transactions fetch caps at 200k rows for the last 90 days. If your volume is higher, switch to a server-computed daily-aggregate table.
- Card and transaction status codes (`A`, `SUPL`, `C`, etc.) are stored verbatim. The UI treats `status='A'` as active and everything else as inactive; `state` and `risk_level` are loaded but unused.
