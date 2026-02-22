# 60–120s Screen Recording Script (Local Demo)

## 0–10s: Context
- Show WP Admin dashboard.
- Say: “Local WordPress demo: Quick Fix Kit — fast diagnostics + exportable report.”

## 10–40s: Show the tool
- Go to Tools → Quick Fix Kit.
- Pause on the status table:
  - PHP version, memory_limit
  - WP_DEBUG / WP_DEBUG_LOG
  - WP-Cron status
  - WooCommerce version (if installed)

## 40–70s: Generate diagnostic report
- Click “Generate Diagnostic Report (.txt)”
- Open the downloaded file and scroll:
  - header/environment
  - active plugins section
  - cron/debug section

## 70–100s: Optional logging proof
- In terminal:
  - list logs directory
  - tail last lines of the latest log (if enabled)

## 100–120s: Close
- End on Quick Fix Kit page.
- Say: “One click to capture baseline + share a sanitized report for troubleshooting.”