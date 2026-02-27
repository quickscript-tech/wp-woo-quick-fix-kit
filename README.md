# WP/Woo Quick Fix Kit (MU-plugin) — Local Demo Deliverable

A minimal, **safe**, reproducible WordPress diagnostics “quick fix kit” delivered as a **Must-Use plugin (MU-plugin)**.

This repo is built as a portfolio project that mirrors real client work: fast triage, clean evidence artifacts, and zero-destructive behavior. It runs fully locally (Docker-first) and does not depend on external services.

---

## Why this exists (client-facing value)

When a WordPress/WooCommerce site breaks, the first 5–10 minutes are usually wasted answering basic questions:

- What PHP version is the site running?
- Is the memory limit too low?
- Are debug flags enabled?
- Is WP-Cron disabled?
- What plugins are active right now?
- Is WooCommerce present and which version?

**Quick Fix Kit** puts those answers in one place and exports a **sanitized diagnostic report** in one click, so you can:
- identify likely causes faster,
- hand off clean evidence to another developer,
- keep triage consistent across incidents.

---

## Safety guarantees (important)

This MU-plugin is intentionally conservative:

- **No destructive actions** (no disabling plugins, no DB migrations, no file deletions).
- The admin page **only reads** environment/status information.
- Logging is **opt-in** via a constant in `wp-config.php`.
- Logs are written under `wp-content/uploads/quickfix-kit/logs/` and protected best-effort via `.htaccess` + `index.php`.
- The downloadable report includes **best-effort sanitization** to redact common secrets/tokens/keys patterns.

> This is a portfolio/local demo tool. In real client production environments, enabling debug flags or exporting reports should be done with client approval and appropriate access controls.

---

## Features (current)

### Admin page: **Tools → Quick Fix Kit**
Displays:
- PHP version
- `memory_limit`
- `WP_DEBUG` and `WP_DEBUG_LOG` status
- Quick Fix Kit logging toggle status (`QFK_DEBUG_LOG`)
- WP-Cron status (enabled/disabled)
- Best-effort “WP-Cron last run” approximation
- Active plugins list (paths)
- WooCommerce detected + version (if present)

### Safe file logging (opt-in)
- Toggle via `define('QFK_DEBUG_LOG', true);` in `wp-config.php`
- Writes logs to:
  - `wp-content/uploads/quickfix-kit/logs/quickfix-kit-YYYY-MM-DD.log`

### One-click downloadable diagnostic report
- Button: **Generate Diagnostic Report (.txt)**
- Outputs a plain `.txt` with key environment and plugin information
- Best-effort sanitization applied to common secret patterns

---

## Repo structure (important paths)

- Docker environment:
  - `docker-compose.yml`
  - `.env.example`
- MU-plugin loader (required by WordPress MU autoloading):
  - `wp-content/mu-plugins/quickfix-kit.php`
- MU-plugin implementation:
  - `wp-content/mu-plugins/quickfix-kit/quickfix-kit.php`
- Logs directory (created at runtime; structure kept in repo):
  - `wp-content/uploads/quickfix-kit/logs/`

---

## Quick start (Docker)

### Requirements
- Docker + Docker Compose

### Boot WordPress locally
From repo root:

```bash
cp .env.example .env
docker compose up -d

```

Open:

- `http://localhost:8080`
  Complete the WordPress installer.

## Confirm MU-plugin files are visible in the container

```bash
docker compose exec wordpress bash -lc "ls -la /var/www/html/wp-content/mu-plugins && ls -la /var/www/html/wp-content/mu-plugins/quickfix-kit"
```

## Use the tool

- Go to **WP Admin → Tools → Quick Fix Kit**
- Click **Generate Diagnostic Report (.txt)** to download the report

---

## Enable logging (optional)

Logging is **off by default.**

Add this line to `/var/www/html/wp-config.php` above the “That’s all…” line:

```php
define('QFK_DEBUG_LOG', true);
```

If your container image doesn’t have nano, you can insert the line via sed:
```bash
docker compose exec wordpress bash -lc "sed -i '/That.s all, stop editing!/i define('\''QFK_DEBUG_LOG'\'', true);\n' /var/www/html/wp-config.php"
docker compose exec wordpress bash -lc "grep -n \"QFK_DEBUG_LOG\" /var/www/html/wp-config.php"
```

### Verify logs are written

Visit the Quick Fix Kit page once, then:
```bash
docker compose exec wordpress bash -lc "ls -la /var/www/html/wp-content/uploads/quickfix-kit/logs && tail -n 50 /var/www/html/wp-content/uploads/quickfix-kit/logs/quickfix-kit-$(date -u +%F).log || true"
```
## LocalWP fallback (if Docker is unavailable)

1. Create a new LocalWP site
2. Copy these into the LocalWP site’s `wp-content/`:
  - `mu-plugins/quickfix-kit.php`
  - `mu-plugins/quickfix-kit/quickfix-kit.php`
3. Ensure this folder exists:
  - `uploads/quickfix-kit/logs/`
4. Visit **WP Admin → Tools → Quick Fix Kit**
