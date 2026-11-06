# NZ Lead Capture + SEO

A WordPress plugin demo by **Nasir Naseer** — lead-capture form, admin lead inbox, and lightweight on-page SEO. Built as a portfolio piece showing real, production-style WordPress plugin code: custom database table, shortcode + AJAX, admin list/status workflow, CSV export, REST API, nonces, capabilities and prepared statements throughout.

> **Line count:** 900+ lines of PHP/JS/CSS (main plugin file + 5 classes + assets). That clears the 300+ line plugin-code gate used by employers such as Crowd Favorite, which requires a GitHub link showing a WordPress plugin or significant feature.

## What it does

- **Lead form — `[nz_lead_form]`**
  - Fields: name, email, phone/WhatsApp, service, project message
  - AJAX submit with a plain-POST fallback if JavaScript is off
  - Nonce + honeypot spam protection, server-side validation on every path
  - Custom title/button via shortcode attributes: `[nz_lead_form title="Book a free call" button="Book now"]`
- **Admin lead inbox — WordPress → Leads**
  - Status workflow: New → Contacted → Won / Lost, with counts per status
  - Delete with confirmation, paginated list, CSV export
  - Notification email to the site owner on every new lead
- **Settings — Leads → Settings**
  - Notification email, success message, business name for schema, SEO on/off
- **Lightweight SEO**
  - Meta description (excerpt → tagline, capped at 160 chars), Open Graph tags
  - LocalBusiness JSON-LD on the front page
  - Stays quiet when Yoast, Rank Math or All in One SEO is active — it only fills gaps, never overwrites an SEO plugin
- **REST API — `wp-json/nzlc/v1/leads`**
  - `GET` — admin only (`manage_options`), filter by status, paginated
  - `POST` — public, nonce-checked, same validation as the form

## Install

1. Copy the `nz-lead-capture` folder into `wp-content/plugins/`
2. WordPress admin → Plugins → activate **NZ Lead Capture + SEO**
3. Add `[nz_lead_form]` to any page
4. See submissions in WordPress → **Leads**

Requires WordPress 6.0+ and PHP 8.0+.

## Files

| File | What it does |
| --- | --- |
| `nz-lead-capture.php` | Plugin header, activation (creates the leads table), boot, asset loading |
| `includes/class-nzlc-storage.php` | Custom table access — insert, list, counts, status, delete, CSV |
| `includes/class-nzlc-form.php` | Shortcode, validation, AJAX + plain-POST handling, owner notification |
| `includes/class-nzlc-admin.php` | Admin menu, lead list, status/delete/export actions, settings |
| `includes/class-nzlc-seo.php` | Meta description, Open Graph, LocalBusiness schema |
| `includes/class-nzlc-rest.php` | REST routes for reading and submitting leads |
| `assets/css/frontend.css` / `assets/js/frontend.js` | Form styling and AJAX submission |
| `uninstall.php` | Drops the leads table and removes options on plugin deletion |

## Verify locally

```bash
php -l nz-lead-capture.php
php -l includes/class-nzlc-*.php
php tests/test-plugin.php   # WordPress-function stubs: shortcode, validation, storage SQL
```

## Notes

- Leads are stored in a dedicated table (`{prefix}nzlc_leads`), never as posts — so the inbox stays fast and exportable.
- Deactivation keeps lead data; deletion (uninstall) removes the table and options.
- Demo only in the sense that it ships with no real client data — the code itself is production-style and installable on any WordPress site.
