# WP/Woo Quick Fix (Local Demo)

A minimal, **safe**, reproducible WordPress diagnostics "quick fix kit" built as a Must-Use plugin (MU-plugin). 
Designed as a portfolio deliverable for fast triage of common WordPress/WooCommerce incidents.

## What it solves (fast triage signals)
- "What environment am I in?" (PHP, memory_limit, key WP debug flags)
- "Is WP-Cron disabled / is anything scheduled?"
- "What plugins are acive right now?"
- "Is WooCommerce present, and what version?"
- "Can I generate a sanitized diagnostic report in one click?"

## Safety guarantees
- **No destructive actions** (no DB writes beyond standard WP reads, no plugin toggling, no file deletions)
- Best-effort log directory hardening (`.htaccess`, `index.php`)
- Report output includes **best-effort sanitization** for common secrets/tokens/keys patterns

## Features
- Togleable file logging via `QFK_DEBUG_LOG` in `wp-config.php`
- Admin page: **Tools > Quick Fix Kit**
- Safe logs to: `wp-content/uploads/quickfix-kit/logs/`
- One-click **Generate Diagnostic Report(.txt)** (download)

---

## Quick start (Docker)
### Prereqs
- Docker + Docker Compose

### Boot
```bash 
cp .env.example .env
