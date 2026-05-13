-- ══════════════════════════════════════════════════════════════════════
--  BaaS Monitor — Migration v6 (DWH-driven schema)
--
--  Adds the tables the bank's DWH will populate from daily CSV dumps:
--    • cards         — one row per BaaS card (mirrors DWH "Cards" feed)
--    • transactions  — one row per card transaction (mirrors DWH feed,
--                       with TX_ID from DWH as the primary key)
--    • prod_types    — lookup: PROD_TYPE code (BAASVCGREENA, …) → partner_id
--
--  Replaces the previous static aggregate tables:
--    • mcc_data      → view over transactions + mcc_labels
--    • country_data  → view over transactions + country_labels
--
--  Adds two label tables that hold the human-readable descriptions
--  (descriptions are NOT in the DWH feed):
--    • mcc_labels        — MCC code → category label (Azerbaijani)
--    • country_labels    — 3-letter country code → name + flag emoji
--
--  Idempotent: safe to re-run.
-- ══════════════════════════════════════════════════════════════════════

BEGIN;

-- ── 1. CARDS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cards (
  card_id              TEXT        PRIMARY KEY,        -- e.g. "440553KISFUM7708"
  branch               TEXT,                            -- 'BANK'
  card_order_no        BIGINT,
  issue_date           DATE,
  expired_date         DATE,
  customer_no          BIGINT,                          -- always 1 today
  card_currency_no     TEXT,                            -- '944' = AZN
  account_id           TEXT,                            -- IBAN
  prod_type            TEXT        NOT NULL,            -- joins prod_types.code
  prod_type_descr      TEXT,                            -- e.g. 'Visa Classic GreenPay AZN'
  credit_limit         NUMERIC(18,2),
  cr_agr_no            TEXT,
  overdraft            NUMERIC(18,2),
  card_agr_closed      TIMESTAMPTZ,
  status               TEXT,                            -- 'A' = active, anything else = inactive
  state                TEXT,                            -- DWH lifecycle code (unused by UI today)
  risk_level           TEXT,                            -- DWH risk code  (unused by UI today)
  card_agr_inserted_by TEXT,
  card_agr_created     TIMESTAMPTZ,
  spec_acnt_id         TEXT,
  closed               TIMESTAMPTZ,
  synced_at            TIMESTAMPTZ DEFAULT NOW()        -- when this row was last upserted
);

CREATE INDEX IF NOT EXISTS idx_cards_prod_type   ON cards(prod_type);
CREATE INDEX IF NOT EXISTS idx_cards_issue_date  ON cards(issue_date);
CREATE INDEX IF NOT EXISTS idx_cards_status      ON cards(status);

-- ── 2. TRANSACTIONS ─────────────────────────────────────────────────
-- TX_ID comes from DWH and is the natural primary key, so we don't need
-- a synthetic id. Rows are idempotent on TX_ID (upsert on re-sync).
CREATE TABLE IF NOT EXISTS transactions (
  tx_id        TEXT        PRIMARY KEY,                 -- DWH transaction id
  card_id      TEXT        NOT NULL REFERENCES cards(card_id) ON DELETE CASCADE,
  prod_type    TEXT        NOT NULL,                    -- denormalized from cards
  amt_ccy_card NUMERIC(18,2) NOT NULL,                  -- amount in card currency (AZN)
  op_date      DATE        NOT NULL,
  mcc_code     TEXT,                                    -- joins mcc_labels.mcc
  merch_cntry  TEXT,                                    -- 3-letter ISO (AZE, TUR, …) — joins country_labels.code
  op_type      TEXT,                                    -- 'C' (credit) / 'D' (debit)
  synced_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tx_card_id     ON transactions(card_id);
CREATE INDEX IF NOT EXISTS idx_tx_prod_type   ON transactions(prod_type);
CREATE INDEX IF NOT EXISTS idx_tx_op_date     ON transactions(op_date);
CREATE INDEX IF NOT EXISTS idx_tx_mcc_code    ON transactions(mcc_code);
CREATE INDEX IF NOT EXISTS idx_tx_merch_cntry ON transactions(merch_cntry);

-- ── 3. PROD_TYPES (lookup) ──────────────────────────────────────────
-- Maps a DWH PROD_TYPE code to a partner. One partner CAN own many codes.
CREATE TABLE IF NOT EXISTS prod_types (
  code        TEXT PRIMARY KEY,                          -- 'BAASVCGREENA'
  description TEXT NOT NULL DEFAULT '',                  -- 'Visa Classic GreenPay AZN'
  partner_id  BIGINT REFERENCES partners(id) ON DELETE SET NULL,
  currency    TEXT DEFAULT 'AZN',
  active      BOOLEAN NOT NULL DEFAULT true,
  notes       TEXT NOT NULL DEFAULT '',
  updated_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_prod_types_partner ON prod_types(partner_id);

-- ── 4. LABEL TABLES (UI metadata not in DWH) ────────────────────────
CREATE TABLE IF NOT EXISTS mcc_labels (
  mcc   TEXT PRIMARY KEY,
  label TEXT NOT NULL DEFAULT ''
);

-- Seed MCC labels (Azerbaijani). Add more rows as needed.
INSERT INTO mcc_labels (mcc, label) VALUES
  ('4111','Nəqliyyat xidmətləri'),
  ('4112','Sərnişin daşımaları (dəmiryolu)'),
  ('4814','Telekommunikasiya'),
  ('5411','Ərzaq mağazaları'),
  ('5311','Universal mağazalar'),
  ('5541','Yanacaqdoldurma stansiyaları'),
  ('5732','Elektronika'),
  ('5812','Restoranlar və kafelər'),
  ('5912','Əczaxanalar'),
  ('5999','Digər pərakəndə satış'),
  ('6011','ATM-dən nağd çıxarış'),
  ('6012','Maliyyə xidmətləri'),
  ('6532','Pul köçürmələri'),
  ('7011','Otellər və mehmanxanalar')
ON CONFLICT (mcc) DO UPDATE SET label = EXCLUDED.label;

CREATE TABLE IF NOT EXISTS country_labels (
  code TEXT PRIMARY KEY,                                 -- 3-letter ISO (AZE)
  name TEXT NOT NULL DEFAULT '',
  flag TEXT NOT NULL DEFAULT ''
);

INSERT INTO country_labels (code, name, flag) VALUES
  ('AZE','Azərbaycan',     '🇦🇿'),
  ('TUR','Türkiyə',        '🇹🇷'),
  ('GEO','Gürcüstan',      '🇬🇪'),
  ('RUS','Rusiya',         '🇷🇺'),
  ('ARE','BƏƏ',            '🇦🇪'),
  ('DEU','Almaniya',       '🇩🇪'),
  ('USA','ABŞ',            '🇺🇸'),
  ('GBR','Böyük Britaniya','🇬🇧'),
  ('FRA','Fransa',         '🇫🇷'),
  ('ITA','İtaliya',        '🇮🇹')
ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, flag = EXCLUDED.flag;

-- ── 5. REPLACE mcc_data / country_data WITH VIEWS ───────────────────
-- The HTML reads these by name; views with the same column shapes keep
-- the existing UI working without changes.

DROP TABLE IF EXISTS mcc_data     CASCADE;
DROP TABLE IF EXISTS country_data CASCADE;

CREATE OR REPLACE VIEW mcc_data AS
SELECT
  t.mcc_code                                            AS mcc,
  COALESCE(l.label, t.mcc_code)                         AS label,
  COUNT(*)::int                                          AS txn_count,
  COALESCE(SUM(t.amt_ccy_card), 0)::numeric(18,2)        AS txn_volume
FROM transactions t
LEFT JOIN mcc_labels l ON l.mcc = t.mcc_code
WHERE t.mcc_code IS NOT NULL AND t.mcc_code <> ''
GROUP BY t.mcc_code, l.label
ORDER BY txn_volume DESC;

CREATE OR REPLACE VIEW country_data AS
SELECT
  COALESCE(NULLIF(t.merch_cntry,''),'XXX')               AS code,
  COALESCE(l.name, NULLIF(t.merch_cntry,''), 'Unknown')  AS name,
  COALESCE(l.flag, '')                                   AS flag,
  COUNT(*)::int                                          AS txn_count,
  COALESCE(SUM(t.amt_ccy_card), 0)::numeric(18,2)        AS txn_volume
FROM transactions t
LEFT JOIN country_labels l ON l.code = t.merch_cntry
GROUP BY 1, l.name, t.merch_cntry, l.flag
ORDER BY txn_volume DESC;

-- ── 6. RLS / POLICIES ───────────────────────────────────────────────
-- HTML-mode: the anon key reads/writes everything. Drop-then-create is
-- idempotent. Same pattern as migration v5.

ALTER TABLE cards          ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions   ENABLE ROW LEVEL SECURITY;
ALTER TABLE prod_types     ENABLE ROW LEVEL SECURITY;
ALTER TABLE mcc_labels     ENABLE ROW LEVEL SECURITY;
ALTER TABLE country_labels ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "allow_all" ON cards;
DROP POLICY IF EXISTS "allow_all" ON transactions;
DROP POLICY IF EXISTS "allow_all" ON prod_types;
DROP POLICY IF EXISTS "allow_all" ON mcc_labels;
DROP POLICY IF EXISTS "allow_all" ON country_labels;

CREATE POLICY "allow_all" ON cards          FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON transactions   FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON prod_types     FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON mcc_labels     FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON country_labels FOR ALL TO anon USING (true) WITH CHECK (true);

-- Views inherit from their underlying tables; PostgREST will surface them
-- once anon has SELECT on those underlying tables (which the allow_all
-- policies above grant via the FOR ALL clause).

COMMIT;

-- ══════════════════════════════════════════════════════════════════════
-- NEXT STEPS AFTER RUNNING THIS MIGRATION
--
-- 1. Populate prod_types so the UI can join cards/transactions to
--    your partners table. Example:
--
--       INSERT INTO prod_types (code, description, partner_id) VALUES
--         ('BAASVCGREENA', 'Visa Classic GreenPay AZN', 1),  -- GreenPay
--         ('BAASVCEPULA',  'Visa Classic EPUL AZN',     2);  -- EPUL
--
--    Use whatever partner_id values correspond to the rows in your
--    partners table. The UI shows "(unmapped)" for cards/transactions
--    whose prod_type isn't in this lookup yet.
--
-- 2. Have your DWH job upsert into:
--       cards         → upsert on card_id  (DWH is source of truth)
--       transactions  → upsert on tx_id    (idempotent re-runs)
--    Skip any DWH rows with malformed dates rather than inserting them.
--
-- 3. After each daily CSV dump, recompute packages.issued/rem/pct from
--    the cards table. A simple example query you can run from the job:
--
--       UPDATE packages p SET
--         issued = (
--           SELECT COUNT(*) FROM cards c
--           JOIN prod_types pt ON pt.code = c.prod_type
--           WHERE pt.partner_id = p.partner_id
--             AND c.issue_date BETWEEN p.start_date AND p.end_date
--         ),
--         rem = GREATEST(0, p.size - (
--           SELECT COUNT(*) FROM cards c
--           JOIN prod_types pt ON pt.code = c.prod_type
--           WHERE pt.partner_id = p.partner_id
--             AND c.issue_date BETWEEN p.start_date AND p.end_date
--         )),
--         pct = ROUND(
--           100.0 * (
--             SELECT COUNT(*) FROM cards c
--             JOIN prod_types pt ON pt.code = c.prod_type
--             WHERE pt.partner_id = p.partner_id
--               AND c.issue_date BETWEEN p.start_date AND p.end_date
--           ) / NULLIF(p.size, 0),
--         2);
-- ══════════════════════════════════════════════════════════════════════

-- ── Verify ──────────────────────────────────────────────────────────
-- SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename IN
--   ('cards','transactions','prod_types','mcc_labels','country_labels');
-- SELECT viewname FROM pg_views WHERE schemaname='public' AND viewname IN ('mcc_data','country_data');
-- SELECT * FROM mcc_labels;
-- SELECT * FROM country_labels;
