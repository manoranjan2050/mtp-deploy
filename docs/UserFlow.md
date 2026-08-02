# User Flow — MTP Deploy

## Flow 1 — First run
1. Operator installs MTP Deploy on a fresh server (or locally for dev).
2. Visits the panel URL → redirected to **Register** (only allowed while zero users
   exist; afterwards Register is admin-invite-only — see Security.md).
3. Registers → automatically assigned `super-admin` role.
4. Prompted (not forced) to enable 2FA from the Profile page.
5. Lands on the Dashboard (Module 2).

## Flow 2 — Day-to-day login
1. Visits panel → **Login** (email + password).
2. If 2FA enabled → prompted for a 6-digit TOTP code (or a recovery code).
3. Session recorded in `sessions` table, visible later under Profile → Sessions.
4. Failed attempts are rate-limited (5/minute per IP+email pair) and logged to the
   activity log.

## Flow 3 — Forgot password
1. **Forgot Password** page → enter email.
2. If the email exists, a signed, time-limited reset link is emailed (Laravel's
   built-in password broker). No account-enumeration signal is given either way —
   the UI shows the same "if that email exists, a link was sent" message.
3. User sets a new password → all other sessions for that user are invalidated.

## Flow 4 — Admin manages users & roles (later in Module 1)
1. `super-admin`/`admin` opens **Users** resource in Filament.
2. Invites a new user by email (creates a `users` row with a random password +
   forces a password-reset flow on first login, or sends an invite link — decided
   during Module 1 build).
3. Assigns a role (`admin`/`developer`/`viewer`) via the Filament form, backed by
   `spatie/laravel-permission`.
4. Can suspend (`is_active = false`) a user without deleting their record/history.

## Flow 5 — Deploy a Laravel site (target end-state, Modules 3–6)
1. Website Manager → **Create Website** → enter domain, PHP version, doc root.
   System provisions nginx vhost + PHP-FPM pool + (optional) auto SSL.
2. Deployment tab → connect GitHub repo (OAuth or PAT), pick branch.
3. First deploy triggered manually (**Deploy** button) → pipeline runs
   `git pull` → `composer install` → `.env` template applied →
   `artisan migrate --force` → `artisan config:cache` → `artisan queue:restart`.
4. Enable **Auto Deploy** → future `git push` to that branch hits the webhook →
   same pipeline runs unattended, with Slack/Telegram notification on
   success/failure (Module 16) and one-click **Rollback** to the previous commit
   if it breaks (Module 5).

## Flow 6 — Respond to an incident (target end-state, Modules 14/15/20)
1. Alert fires (disk >90%, queue worker down, SSL expiring in 7 days).
2. Notification delivered via configured channel (Module 16).
3. Operator opens Dashboard → drills into Monitoring/Logs for the affected site.
4. AI Assistant (Module 20) summarizes the relevant log lines and suggests a fix.
5. Operator acts (restart service, extend disk, renew cert) from the same panel —
   no SSH session required, though Terminal (Module 8) remains available for
   anything the UI doesn't cover yet.
