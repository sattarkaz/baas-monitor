-- ══════════════════════════════════════════════════════════════════════
--  BaaS Monitor — Migration v5 (HTML / browser-direct mode)
--
--  Bu migration v4-ün bəzi dəyişikliklərini geri qaytarır, çünki
--  brauzerdən birbaşa Supabase-ə bağlanan HTML SPA versiyası
--  fərqli tələblər irəli sürür:
--
--    1. anon açar ilə cədvəlləri oxumaq lazımdır → RLS-i açırıq
--    2. brauzer bcrypt-i təsdiqləyə bilmir → şifrələri plaintext qaytarırıq
--    3. partner-docs bucket-inə anon yüklənmə icazəsi
--
--  This migration UNDOES parts of v4 because the HTML SPA in this
--  package talks to Supabase directly from the browser:
--    1. The browser uses the anon key → we re-enable broad RLS
--    2. The browser can't verify bcrypt → passwords return to plaintext
--    3. Anon needs to upload to the partner-docs Storage bucket
--
--  SECURITY:
--  After this migration, anyone with the anon key + project URL can read,
--  write, and delete every row in these tables. Acceptable for an internal
--  bank tool with trusted users; NOT acceptable for anything public-facing.
-- ══════════════════════════════════════════════════════════════════════

-- ── 1. Restore plaintext demo passwords ──────────────────────────────
-- These match what the HTML SPA's doLogin() expects.
-- CHANGE THEM IMMEDIATELY after first login via the Users page.

UPDATE sys_users SET password = 'admin123'   WHERE login = 'admin';
UPDATE sys_users SET password = 'analyst123' WHERE login = 'analyst';
UPDATE sys_users SET password = 'manager123' WHERE login = 'manager';
UPDATE sys_users SET password = 'manager123' WHERE login = 'manager2';
UPDATE sys_users SET password = 'viewer123'  WHERE login = 'viewer1';

-- ── 2. Re-open RLS so the anon key can read/write the app tables ─────
-- (v4 dropped these "allow_all" policies; we put them back.)

CREATE POLICY "allow_all" ON partners      FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON packages      FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON sys_users     FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON partner_docs  FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON mcc_data      FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON country_data  FOR ALL TO anon USING (true) WITH CHECK (true);
CREATE POLICY "allow_all" ON fee_packages  FOR ALL TO anon USING (true) WITH CHECK (true);

-- ── 3. Storage policy for partner-docs bucket ────────────────────────
-- The HTML partner-detail modal uploads files via the anon key. Without
-- explicit policies on storage.objects, anon is denied by default.

CREATE POLICY "anon_select_partner_docs" ON storage.objects
  FOR SELECT TO anon
  USING (bucket_id = 'partner-docs');

CREATE POLICY "anon_insert_partner_docs" ON storage.objects
  FOR INSERT TO anon
  WITH CHECK (bucket_id = 'partner-docs');

CREATE POLICY "anon_update_partner_docs" ON storage.objects
  FOR UPDATE TO anon
  USING (bucket_id = 'partner-docs')
  WITH CHECK (bucket_id = 'partner-docs');

CREATE POLICY "anon_delete_partner_docs" ON storage.objects
  FOR DELETE TO anon
  USING (bucket_id = 'partner-docs');

-- ── Yoxlama / Verify ─────────────────────────────────────────────────
-- SELECT login, password FROM sys_users;
-- SELECT tablename, policyname FROM pg_policies
--   WHERE tablename IN ('partners','packages','sys_users','partner_docs','mcc_data','country_data','fee_packages');
-- SELECT policyname FROM pg_policies WHERE schemaname='storage' AND tablename='objects';
