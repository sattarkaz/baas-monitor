# BaaS Monitor — Static HTML on GitHub Pages

A single-page web app that monitors BaaS partners, card packages, MCC and country breakdowns, users and roles. It runs entirely in the browser and talks directly to a Supabase project. No server, no PHP, no build step.

## What you get

```
index.html                                 ← the whole app, one file
.gitignore
README.md                                  ← this file
sql/supabase_setup.sql                     ← base schema
sql/supabase_migration_v2.sql              ← schema additions
sql/supabase_migration_v3.sql              ← password column, mcc_data, country_data
sql/supabase_migration_v4.sql              ← (server-mode lockdown — DO NOT RUN for HTML mode)
sql/supabase_migration_v5_html_mode.sql    ← undoes v4 for browser-direct access
```

## One-time Supabase setup

You've already done most of this from earlier turns (migrations v2, v3, v4 were applied; the `partner-docs` bucket was created). To finish the switch to HTML mode:

1. Open the Supabase SQL editor.
2. Paste the contents of `sql/supabase_migration_v5_html_mode.sql` and run it. This restores plaintext demo passwords, re-opens RLS on the app tables so the anon key can read them, and adds storage policies that let the browser upload files to the `partner-docs` bucket.

That's the only Supabase action you need to take now.

## Putting it on GitHub Pages

GitHub Pages is GitHub's built-in static hosting. It serves whatever `index.html` is at the root of your repo. There's nothing to install or configure beyond the steps below.

1. Sign in to github.com, click **+** → **New repository**.
2. Name it something like `baas-monitor`. Choose **Public** if you want anyone to be able to visit the URL, or **Private** if only you and invited collaborators should access the code (note: GitHub Pages on a free Private repo can only be made *Private* via Pages settings; on a Pro account it can be served behind GitHub login).
3. Don't tick "Add a README" — yours is already in the folder.
4. Click **Create repository**.
5. On the empty-repo page, click **uploading an existing file**.
6. Open the project folder on your computer. Press Cmd+Shift+. (Mac) or enable hidden files (Windows) so `.gitignore` is visible. Select everything inside the folder (drag-select or Cmd/Ctrl+A), and drag it into the GitHub upload area. The `sql/` folder will upload as a folder automatically.
7. Scroll down. Write a commit message like `Initial commit` and click **Commit changes**.
8. Now turn on Pages. In the repo, click **Settings** → **Pages** (left sidebar).
9. Under **Source**, choose **Deploy from a branch**, then set **Branch** to `main` and folder to `/ (root)`. Save.
10. GitHub will say "Your site is ready to be published at `https://<your-username>.github.io/baas-monitor/`." Within a minute or two, that URL goes live and shows the login page.

To update the app later: edit `index.html` on your computer (or directly on GitHub), commit, and GitHub Pages republishes within a minute.

## Logging in

After migration v5 has been applied, these demo logins work:

| Login    | Password   | Role          |
|----------|------------|---------------|
| admin    | admin123   | Administrator |
| analyst  | analyst123 | Analyst       |
| manager  | manager123 | Manager       |
| manager2 | manager123 | Manager       |
| viewer1  | viewer123  | Viewer        |

**Change these immediately** through the Users page once you log in as admin. They're public knowledge (they're in the migration file in this repo).

## How the data flow works

`index.html` has a small Supabase client written in plain JavaScript at the top of its `<script>` block. On page load, the `loadData()` function calls Supabase REST for the seven tables — `partners`, `packages`, `sys_users`, `partner_docs`, `mcc_data`, `country_data`, `fee_packages` — and stores the rows in JavaScript arrays. All the views (Dashboard, Partners, Transactions, Users) read from those arrays. Edits in the UI POST/PATCH back to Supabase and re-render. File uploads from the partner detail modal go to Supabase Storage (`partner-docs` bucket).

Charts on the Dashboard and Transactions pages show zeros for transaction volumes — there's no transactions table in Supabase yet. When you create one, swap the body of `genDailyData()` (near the bottom of the `<script>`) to fetch from your real source.

## Security posture

Because this is browser-only, the `anon` Supabase key is sitting in the HTML for anyone who opens DevTools to read. That key, combined with the open RLS policies from migration v5, means anyone who finds your Pages URL can read, write, and delete every row in your Supabase. This is fine for a small internal tool with trusted users on a private repo, and not fine for anything customer-facing.

If your audience widens later, the safer move is to put a backend server in front of Supabase (the PHP version of this project does that) and lock RLS back down. Until then:

- Keep the repo **Private** if possible. The Pages URL is harder to guess if no one's seen the repo.
- Rotate the anon key periodically (Supabase dashboard → Settings → API → "Reset anon key"). Update the value in `index.html` and push.
- Use a Supabase project that holds only this monitoring data — never combine it with sensitive customer PII in the same project.

## Local preview

Just double-click `index.html` to open it in a browser. Everything except the file uploads will work offline-of-Supabase if you're not connected, but as soon as you log in it'll talk to your live Supabase project.
