-- ═══════════════════════════════════════════════════════════════════════════
--  Migration v7 — partner lifecycle statuses + soft delete
-- ═══════════════════════════════════════════════════════════════════════════
--
--  WHAT THIS DOES
--    1. Adds soft-delete columns to `partners` (deleted_at, deleted_by).
--    2. Creates `partner_status_history` — who changed a status, when,
--       and old status -> new status.
--    3. Migrates the old two-value vocabulary to the new five-value one:
--            active   -> active    (unchanged)
--            inactive -> on_hold
--       and seeds a history row for every partner it moves, so the migration
--       itself is auditable.
--    4. Constrains `partners.status` to the five allowed values.
--
--  STATUS VALUES  (DB value -> UI label)
--       negotiation -> Danışıqlar / Negotiation
--       signing     -> Müqavilə imzalanması / Signing agreement
--       active      -> Aktiv / Active
--       on_hold     -> Gözləmədə / On hold
--       closed      -> Bağlı / Closed
--
--  SAFETY
--    • Runs in one transaction — either all of it lands or none of it.
--    • Idempotent: re-running is a no-op (step 3 finds no 'inactive' rows the
--      second time, and every DDL step is IF NOT EXISTS / DROP IF EXISTS).
--    • Nothing is deleted. No existing column is dropped or renamed.
--    • Expected on the current database: 24 partners — 8 active (untouched),
--      16 inactive (-> on_hold).
--
--  ROLLBACK  (if ever needed)
--       ALTER TABLE partners DROP CONSTRAINT IF EXISTS partners_status_check;
--       UPDATE partners SET status='inactive' WHERE status='on_hold';
--       -- deleted_at / deleted_by and partner_status_history can stay; the
--       -- old UI ignores columns it does not know about.
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

-- ── 1. soft-delete columns ────────────────────────────────────────────────
ALTER TABLE partners ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;
ALTER TABLE partners ADD COLUMN IF NOT EXISTS deleted_by TEXT;

COMMENT ON COLUMN partners.deleted_at IS
  'Soft delete marker. NULL = live. Set = hidden from the UI, but every '
  'invoice, addendum, transaction and audit entry referencing this partner '
  'keeps resolving.';

CREATE INDEX IF NOT EXISTS idx_partners_deleted_at ON partners(deleted_at);

-- ── 2. status history ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS partner_status_history (
  id          BIGSERIAL   PRIMARY KEY,
  partner_id  BIGINT      NOT NULL REFERENCES partners(id) ON DELETE CASCADE,
  old_status  TEXT,                      -- NULL = partner was just created
  new_status  TEXT        NOT NULL,
  changed_by  TEXT,                      -- sys_users.name of the actor
  changed_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_psh_partner
  ON partner_status_history(partner_id, changed_at DESC);

-- RLS posture matches migration v5 (browser talks to Supabase directly).
ALTER TABLE partner_status_history ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS allow_all ON partner_status_history;
CREATE POLICY allow_all ON partner_status_history
  FOR ALL USING (true) WITH CHECK (true);

-- ── 3. data migration: inactive -> on_hold ────────────────────────────────
-- Seed the history first, while the old value is still readable.
INSERT INTO partner_status_history (partner_id, old_status, new_status, changed_by)
SELECT id, 'inactive', 'on_hold', 'migration_v7'
FROM   partners
WHERE  lower(status) = 'inactive';

UPDATE partners SET status = 'on_hold' WHERE lower(status) = 'inactive';

-- Fold any stray casing into the canonical value.
UPDATE partners SET status = 'active'  WHERE lower(status) = 'active'  AND status <> 'active';
UPDATE partners SET status = 'closed'  WHERE lower(status) = 'closed'  AND status <> 'closed';

-- Anything unrecognised (or NULL) starts at the top of the pipeline, so the
-- CHECK constraint below cannot fail on legacy junk.
UPDATE partners SET status = 'negotiation'
WHERE  status IS NULL
   OR  status NOT IN ('negotiation','signing','active','on_hold','closed');

-- ── 4. constrain to the five allowed values ───────────────────────────────
ALTER TABLE partners DROP CONSTRAINT IF EXISTS partners_status_check;
ALTER TABLE partners ADD  CONSTRAINT partners_status_check
  CHECK (status IN ('negotiation','signing','active','on_hold','closed'));

COMMIT;

-- ── 5. verification — run this after the migration and eyeball the output ──
--  Expected: active = 8, on_hold = 16, no other rows, 16 history entries.
SELECT status, count(*) AS partners
FROM   partners
WHERE  deleted_at IS NULL
GROUP  BY status
ORDER  BY status;

SELECT count(*) AS seeded_history_rows
FROM   partner_status_history
WHERE  changed_by = 'migration_v7';
