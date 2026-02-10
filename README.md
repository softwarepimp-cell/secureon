# Secureon.cloud

Secureon.cloud is a PHP 8 + MySQL SaaS for managed MySQL backups with encrypted container storage (`.scx`), server-triggered backup dispatch, manual billing approvals, and strict entitlement enforcement.

This codebase now runs an end-to-end workflow:
- Super Admin creates plans/packages
- Customer submits manual payment request
- Super Admin approves
- Subscription and limits activate
- Secureon triggers remote agents on schedule
- Agent creates encrypted backups and uploads to Secureon
- Customer can download `.scx` and decrypted `.sql` backups (when billing is active)

## Current Implementation Status

### Completed
- Marketing + app UI (Tailwind + Alpine + Chart.js)
- Auth (register/login/logout) + CSRF + rate limiting
- Multi-system management
- Server-push trigger model (Secureon cron dispatches remote agent)
- Agent bundle ZIP generation from system detail page
- Secureon Status Badge generator (`secureon-badge.php`) for client platforms
- Trigger security: secret trigger filename, HMAC signature, timestamp window, nonce replay checks, optional IP allowlist
- Backup ingestion pipeline with `.scx` encrypted container
- SQL download by decrypting/decompressing `.scx` on server
- Manual billing loop with Super Admin approval
- Strict billing enforcement across system creation, triggers, uploads, and downloads
- Super Admin moderation controls (suspend, roles, package management, billing approvals)
- Retention and health cron jobs
- System deletion flow (removes DB records and backup files)

### Notes
- Payment gateway is intentionally manual (proof/reference + admin approval)
- Local storage is used (`storage/backups`) with secure streaming downloads
- S3/object storage and chunked upload are not implemented yet

## Tech Stack
- PHP 8+ (vanilla MVC-ish)
- MySQL (PDO, prepared statements)
- Apache (XAMPP compatible)
- TailwindCSS (CDN)
- Alpine.js (CDN)
- Chart.js (CDN)

## Requirements
- PHP 8+
- MySQL 8+ (or compatible)
- Apache with `mod_rewrite`
- Required PHP extensions: `pdo_mysql`, `openssl`, `curl`, `json`, `mbstring`, `zlib`
- Recommended PHP extension: `zip` (Windows fallback uses PowerShell `Compress-Archive`)

## Project Structure

```text
/
  public/
    index.php
    .htaccess
    assests/            # logo + homepage images
  app/
    Core/
    Middleware/
    Controllers/
    Models/
  views/
    layouts/
    marketing/
    auth/
    app/
  storage/
    backups/
    logs/
    tmp/
  scripts/
    cron_dispatch.php
    cron_health.php
    cron_retention.php
  agent/
    secureon-agent.php
    secureon-badge.php.template
    secureon-agent-config.php.example
  database/
    schema.sql
    migrations/
      20260208_server_push.sql
      20260209_manual_billing.sql
      20260209_badge_tokens.sql
  .htaccess
  README.md
```

## Database Setup

### Fresh install
1. Create DB and import schema:

```sql
SOURCE database/schema.sql;
```

### Upgrade existing install
Run migrations in order:

```sql
SOURCE database/migrations/20260208_server_push.sql;
SOURCE database/migrations/20260209_manual_billing.sql;
SOURCE database/migrations/20260209_badge_tokens.sql;
```

These migrations add:
- Server-push trigger fields on `systems`
- Manual billing structures (`payment_requests`, expanded `plans`, expanded `subscriptions`)
- Badge token typing fields on `system_tokens`

## App Configuration

Edit `app/Core/config.php`:

```php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'secureon',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'APP_KEY' => 'change-this-32+chars-secret-key',
    'BASE_URL' => 'https://your-domain.com/secure',
    'STORAGE_PATH' => __DIR__ . '/../../storage',
    'DEV_MODE' => true,
    'DEFAULT_CURRENCY' => 'USD',
    'BILLING_GRACE_DAYS' => 0,
    'MAX_BILLING_MONTHS' => 60,
];
```

### Important config notes
- `APP_KEY` must remain stable. Changing it can break SQL decrypt for older backups.
- `BASE_URL` should match how you access Secureon in browser.
- `DEV_MODE=true` relaxes manual trigger rate-limit behavior to support testing.

## Routing and URL Access (XAMPP)

### Option A (recommended): Apache vhost -> `public/`
Point document root to `.../secure/public`, then app routes run directly.

### Option B (simple htdocs mode)
Keep project in `htdocs/secure` and use root `.htaccess` rewrite.

In this mode, both these patterns are supported by helper logic:
- `BASE_URL = https://your-domain.com/secure`
- `BASE_URL = https://your-domain.com/secure/public`

Logout redirect now auto-handles both styles.

## Storage Paths
Create folders if missing:
- `storage/backups`
- `storage/logs`
- `storage/tmp`

Backups are stored outside web root and are served through PHP after permission checks.

## Default Accounts
`database/schema.sql` seeds a super admin user.

If needed, reset super admin password manually:

```sql
UPDATE users
SET password_hash = '$2y$10$ivV8XVBA.7eweJNdoU304uyGgph728IO9tno..9gt/6u54Kk.BoHm'
WHERE email = 'super@secureon.cloud';
```

Known password for that hash:
- `SuperAdmin123!`

## Manual Billing Loop (Complete)

### 1) Super Admin creates packages
UI routes:
- `GET /admin/packages`
- `GET /admin/packages/new`
- `POST /admin/packages/create`
- `GET /admin/packages/{id}/edit`
- `POST /admin/packages/{id}/update`
- `POST /admin/packages/{id}/toggle`

Plan fields:
- `base_price_monthly`
- `price_per_system_monthly`
- `storage_quota_mb`
- `max_systems`
- `retention_days`
- `min_backup_interval_minutes`
- `is_active`

### 2) User requests billing approval
UI routes:
- `GET /billing`
- `POST /billing/estimate` (AJAX)
- `POST /billing/request`
- `GET /billing/requests`

Pricing formula:

```text
total = (base_price_monthly * months)
      + (price_per_system_monthly * months * requested_systems)
```

Validation rules:
- `months` between `1` and `MAX_BILLING_MONTHS`
- `requested_systems` between `1` and `plan.max_systems`
- proof reference required

### 3) Super Admin approves or declines
UI routes:
- `GET /admin/billing/requests`
- `GET /admin/billing/requests/{id}`
- `POST /admin/billing/requests/{id}/approve`
- `POST /admin/billing/requests/{id}/decline`
- `POST /admin/billing/users/{id}/adjust` (manual admin override)

Approval effect:
- Payment request -> `approved`
- Subscription -> `active`
- `allowed_systems` set from approved request
- `started_at` and `ends_at` set

Decline effect:
- Payment request -> `declined`
- User pending subscription -> `declined`

Notification stubs write to:
- `storage/logs/mail.log`

## Billing Enforcement (Strict)

When billing is inactive/expired:
- System creation blocked
- Agent handshake/start/upload/complete/fail flows blocked
- Trigger dispatch/test blocked
- Backup downloads (`.scx` and `.sql`) blocked
- Dispatch cron skips systems and logs `DISPATCH_SKIPPED_BILLING`

Quota/system limits enforced using active entitlements:
- `allowed_systems`
- `storage_quota_mb`
- `retention_days`
- `min_backup_interval_minutes`

## Server-Push Backup Model

Secureon dispatches HTTPS trigger calls to remote agent URL.

### Dispatch cron
- Script: `scripts/cron_dispatch.php`
- Recommended schedule: every minute

Dispatch conditions:
- user is active
- billing is active
- system has `trigger_url`
- not already running a recent backup
- interval elapsed

Signed request headers:
- `X-Secureon-System`
- `X-Secureon-Timestamp`
- `X-Secureon-Nonce`
- `X-Secureon-Signature` (HMAC SHA-256)

## Agent Bundle Generation

From system detail page:
- Download with placeholders, or
- Prefill DB credentials via AJAX, then download

Route:
- `GET /systems/{id}/download-agent`
- `POST /api/v1/systems/{id}/prepare-agent-bundle` (AJAX prefill)

Generated ZIP includes:
- `secureon-agent/secureon-agent.php`
- `secureon-agent/<trigger_path>.php`
- `secureon-agent/secureon-agent-config.php` (system-specific)
- `secureon-agent/secureon-badge.php` (prefilled status badge include)
- `secureon-agent/.htaccess`
- `secureon-agent/README_INSTALL.txt`
- `secureon-agent/cache/`
- `secureon-agent/logs/`

Build behavior:
- Uses PHP `ZipArchive` when available
- On Windows, falls back to `Compress-Archive` if `zip` extension is missing

## Secureon Status Badge

This feature lets client platforms show a tiny “Secureon status” indicator by adding one include line.

### Dashboard routes
- `POST /systems/{id}/badge-token/create`
- `POST /systems/{id}/badge-token/revoke`
- `GET /systems/{id}/download-badge`

### Badge API route
- `GET /api/v1/badge/status?system_id=...`
- Auth header: `Authorization: Bearer <badge_token>`

### Script install on client platform
1. Open system details in Secureon.
2. Generate badge token.
3. Download `secureon-badge.php`.
4. Upload it to client app, for example `includes/secureon-badge.php`.
5. Include in header or layout:

```php
<?php include __DIR__ . '/includes/secureon-badge.php'; ?>
```

### Badge behavior
- Fixed corner badge (default: bottom-right)
- No JS dependency and no Tailwind dependency
- Scoped CSS only (`.secureon-badge*`)
- Configurable position/theme/minimal mode/link/powered-by
- Dot colors: healthy (green), warning (amber), failed (red), billing/unreachable (gray)
- Click can open Secureon system page (optional)
- Local cache file (`cache/secureon_badge_{system_id}.json`) defaults to 60 seconds
- Network fallback: cURL first, then `file_get_contents` if `allow_url_fopen` is enabled

### Security
- Badge token is separate from agent token (`token_type = badge`)
- Token stored only as hash in DB
- Endpoint rate-limited by token and IP
- Badge access attempts logged in `audit_logs` (`BADGE_STATUS_VIEW`, `BADGE_STATUS_VIEW_DENIED`)
- Billing-inactive systems return `billing_required` status

## Remote Agent Behavior

Trigger endpoint validates:
- POST method
- timestamp window
- nonce replay protection
- signature correctness
- optional allowed IP list
- interval/rate check (can be relaxed in `dev_mode`)

Backup pipeline:
1. handshake
2. backup start
3. DB export (`mysqldump` preferred)
4. fallback exporter if `mysqldump` unavailable
5. gzip compress
6. AES-256-GCM encrypt to `.scx` container
7. upload
8. complete/fail status callback

CLI usage:

```powershell
C:\x\php\php.exe secureon-agent.php backup
C:\x\php\php.exe secureon-agent.php restore --backup-id=123 --target-db=your_db
```

## Backup Downloads and Restore

User dashboard supports:
- `Download .scx` (encrypted container)
- `Download .sql` (server decrypt + decompress)

SQL route:
- `GET /backups/{id}/download-sql`

If SQL decrypt fails, most common cause is key mismatch:
- Agent bundle `master_key` must match Secureon `APP_KEY`

## Dashboard Data

Dashboard metrics are real DB-backed values:
- systems count
- backups/failures in last 24h
- storage used vs quota
- 7-day storage chart
- 30-day success vs fail chart
- latest events polling

Pricing section on marketing pages also reads active plans from DB.

## Cron Jobs

Use the PHP binary from XAMPP:

```powershell
C:\x\php\php.exe scripts\cron_dispatch.php
C:\x\php\php.exe scripts\cron_health.php
C:\x\php\php.exe scripts\cron_retention.php
```

Recommended frequencies:
- `cron_dispatch.php`: every 1 minute
- `cron_health.php`: every 5 to 15 minutes
- `cron_retention.php`: hourly (or daily on low volume)

## API Summary

### Session-auth APIs
- `GET /api/v1/dashboard/metrics`
- `GET /api/v1/dashboard/latest-events`
- `GET /api/v1/systems/{id}/latest-status`
- `POST /api/v1/systems/{id}/trigger-now`
- `POST /api/v1/systems/{id}/test-trigger`
- `POST /api/v1/systems/{id}/prepare-agent-bundle`
- `POST /api/v1/systems/{id}/tokens`
- `POST /api/v1/backups/{id}/sign-download`
- `POST /api/v1/backups/{id}/delete`
- `GET /api/v1/badge/status?system_id=...` (badge token auth)

### Agent token APIs
- `POST /api/v1/agent/handshake`
- `POST /api/v1/agent/backup/start`
- `POST /api/v1/agent/backup/progress`
- `POST /api/v1/agent/backup/complete`
- `POST /api/v1/agent/backup/fail`
- `POST /api/v1/agent/backup/upload`
- `GET /api/v1/agent/backup/restore/{backup_id}`

## Branding and Assets
- Platform logo and homepage images are loaded from `public/assests/`
- `Helpers::logoUrl()` auto-resolves logo path
- Marketing/auth/app layouts use shared logo helper

## Security Controls Implemented
- `password_hash` + `password_verify`
- CSRF tokens for forms
- basic rate limiting for login/token operations
- prepared statements everywhere
- session hardening
- signed trigger calls (HMAC)
- nonce replay protection
- optional agent IP allowlist
- file access through controlled endpoints
- audit logs for key actions

## Troubleshooting

### Dashboard fails with subscription `updated_at` error
A compatibility patch is already in code (`Subscription` model), but migration is still recommended:

```sql
SOURCE database/migrations/20260209_manual_billing.sql;
```

### `php` command not found
Use full path:

```powershell
C:\x\php\php.exe
```

### `mysqldump` not recognized
- Set `mysqldump_path` in agent config
- Fallback exporter is implemented (slower but functional)

### Agent bundle fails with `ZipArchive not found`
- Enable `extension=zip` in `php.ini`, or
- On Windows rely on PowerShell `Compress-Archive` fallback

### Badge shows `Unreachable`
- Verify `secureon_base_url` in `secureon-badge.php`
- Ensure server can reach Secureon over HTTP/HTTPS
- Ensure cURL is enabled, or `allow_url_fopen=On`
- Check badge cache directory write permission (`cache/`)

### Badge says `Invalid badge token`
- Generate a new badge token in system details
- Re-download `secureon-badge.php`
- If tokens were revoked, old scripts stop working by design

### SQL download says `Decryption failed`
- Ensure bundle `master_key` equals Secureon `APP_KEY`
- Generate a new bundle and run a fresh backup

### Trigger returns `rate_limited` while testing
- `DEV_MODE=true` in `app/Core/config.php` relaxes manual trigger checks
- Agent config includes `dev_mode` value from Secureon at bundle generation time

### Logout goes to folder listing
- Ensure `BASE_URL` is correct
- Ensure root `.htaccess` rewrite is present
- Current logout logic auto-targets `/public` when needed

## Suggested Local Test Flow
1. Log in as super admin
2. Create active packages in `/admin/packages`
3. Register a normal user
4. As user, open `/billing`, estimate, and submit payment request
5. As super admin, approve request in `/admin/billing/requests`
6. As user, create system, set interval, open system detail
7. Prepare/download agent bundle (prefill DB credentials)
8. Deploy bundle to target server and set `trigger_url`
9. Click `Test Trigger`, then `Trigger Now`
10. Confirm backup appears and test `.scx` + `.sql` download
11. Generate/download `secureon-badge.php` and include it on the client platform header
12. Run cron scripts and confirm automated dispatch

## Future Enhancements
- Encrypt system secrets at rest
- Chunked upload for large backups
- Queue-based async jobs
- S3/object storage backend
- Real email provider integration
- Webhook/Slack alert channels

---

For implementation details, review:
- `app/Controllers/SystemsController.php`
- `app/Controllers/AdminBillingController.php`
- `app/Controllers/BillingController.php`
- `app/Controllers/AgentApiController.php`
- `app/Controllers/BadgeApiController.php`
- `app/Models/Token.php`
- `scripts/cron_dispatch.php`
- `agent/secureon-agent.php`
