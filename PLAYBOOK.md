# Quick Fix Kit — 30-Minute Triage Playbook

Goal: reach a defensible diagnosis quickly without making destructive changes.
This playbook assumes you can access wp-admin. If you can’t, use the “No wp-admin access” branch.

## Minute 0–3: Baseline capture (always)
1) Open **Tools → Quick Fix Kit**
2) Download the **Diagnostic Report (.txt)** immediately
3) Save it with the incident ticket name (example: `incident-redirect-loop-YYYYMMDD.txt`)

What you’re looking for:
- PHP version and `memory_limit` (too low often causes fatal/memory issues)
- WP_DEBUG flags (may be off in production; don’t force changes blindly)
- WP-Cron enabled/disabled (disabled cron breaks scheduled tasks, subscriptions, etc.)
- Active plugins list (conflict surface)
- WooCommerce detected + version (if present)

---

## Branch A — WSOD / fatal errors (Minute 3–12)
### If you have wp-admin access
1) Use Quick Fix Kit table: confirm `memory_limit`, PHP version
2) Check report “Active plugins” list: identify recent/unknown plugins
3) If logging enabled, check Quick Fix logs for repeated admin hits

Next actions (safe, minimal):
- Temporarily enable WP debug logging **only in local demo** if needed:
  - `WP_DEBUG` + `WP_DEBUG_LOG` (client approval required in real production)
- Identify plugin/theme candidates and reproduce the error path once

### If you do NOT have wp-admin access
Fast non-destructive isolation (local demo / staging only):
1) Rename `wp-content/plugins` to `plugins.off` (forces plugin disable)
2) Re-test front-end/wp-admin access
3) If fixed, restore folder and binary-search plugin subfolders

Deliverable after 12 minutes:
- “Most likely cause: plugin/theme/runtime limit”
- Evidence: report + error log snippet or reproducible trigger path

---

## Branch B — Redirect loops (Minute 3–12)
Common causes:
- Wrong site URL / home URL
- HTTPS / reverse-proxy mismatch
- Cache/security plugins forcing redirects

Checklist:
1) From report: confirm Site URL is expected
2) Check active plugins for caching/security/redirect tooling
3) Validate browser devtools “Network” shows repeated 301/302

Safe next actions (local/staging):
- Disable cache plugin temporarily (prefer staging)
- Confirm canonical URLs (http vs https) and server headers

Deliverable:
- “Redirect loop source isolated to: config vs plugin vs proxy mismatch”
- Evidence: report + devtools screenshot

---

## Branch C — Woo checkout issues (Minute 3–18)
Symptoms:
- Cart/checkout stuck, payments failing, order not created, AJAX errors

Checklist:
1) Confirm WooCommerce detected + version
2) Confirm WP-Cron is enabled (Woo relies on scheduled actions)
3) Check active plugins for payment/shipping integrations
4) Look for server-side limits: `max_execution_time`, memory limit

Safe next actions:
- Reproduce checkout with browser devtools open
- Check Woo status pages (in wp-admin) if available
- Disable non-essential Woo add-ons on staging to isolate

Deliverable:
- “Checkout breakage likely due to: gateway add-on vs AJAX conflict vs cron/limits”
- Evidence: report + failing request screenshot + plugin list

---

## Branch D — Slow wp-admin (Minute 3–30)
High-probability culprits:
- Low PHP resources
- Plugin hooks doing expensive work
- Cron backlog / scheduled tasks misbehaving

Checklist:
1) Confirm `memory_limit` and `max_execution_time`
2) Confirm WP-Cron enabled; check “last run (best-effort)”
3) List active plugins; flag heavy categories (builders, security, analytics, backup)

Safe next actions:
- Measure baseline: time to load Dashboard (screen recording or simple stopwatch)
- Disable suspected plugins on staging only, one-by-one (or in groups)
- If available, enable Query Monitor (free) on local/staging (optional)

Deliverable:
- “Top 1–2 suspects + measured before/after”
- Evidence: baseline timing + after timing + plugin toggles list (staging)
