# Magento 2 Custom Redirect — 404 NoRoute Handler

A lightweight Magento 2 module that automatically recovers visitors from 404 pages by redirecting them to the most relevant active page using 301 redirects. Instead of bouncing customers to a dead end, this module intelligently resolves deleted or disabled URLs to their best matching destination.

## Why Use This Module

When you restructure your Magento 2 store — disable categories, remove products, or migrate from a legacy URL structure — customers and search engines hit 404 pages. Every 404 is a lost visitor and a wasted SEO signal.

This module intercepts every NoRoute (404) request in Magento 2 and resolves it automatically:

- Disabled or deleted category URL: visitor lands on the nearest active parent category, staying in the right section of the store
- Removed product URL: visitor lands on an active category the product belonged to, so they can still browse alternatives
- Legacy URL patterns (e.g. `view-shape/<sku>`): old external links and bookmarks resolve directly to the correct product page
- Unresolvable 404: visitor is sent to the store homepage — no dead ends, no lost sessions

Net result: fewer exits, preserved SEO link equity via 301 signals, and old URLs never fully break.

## How It Works

The module registers an `aroundPlugin` on `Magento\Cms\Controller\Noroute\Index::execute`. On every 404 request in Magento 2:

1. Looks up the requested path in the `url_rewrite` table
2. If a rewrite exists, determines entity type (category or product) and finds the best active redirect target
3. If no rewrite exists, checks legacy URL patterns
4. Falls back to the store homepage if no valid target is found

All redirects are issued as HTTP 301 (permanent), so search engines transfer link equity to the new URL.

## Requirements

- Magento 2.4.4 – 2.4.9
- PHP 8.1 – 8.5

## Installation

### Via Composer (recommended)

```bash
composer require jainamdeveloper/magento2-custom-redirect
bin/magento module:enable Magneto_CustomRedirect
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual Installation

1. Copy the module files to `app/code/Magneto/CustomRedirect/`
2. Run the following commands:

```bash
bin/magento module:enable Magneto_CustomRedirect
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Redirect Logic Reference

| Incoming URL type | Redirect Target |
|---|---|
| Disabled/deleted category | Nearest active parent category |
| Removed/disabled product | First active category the product belonged to |
| Legacy `view-shape/<sku>` URL | Product URL resolved by SKU |
| Any other 404 | Store homepage |

## Keywords

Magento 2 custom redirect, Magento 2 404 redirect, Magento 2 NoRoute plugin, Magento 2 301 redirect, Magento 2 SEO redirect, Magento 2 category redirect, Magento 2 product redirect, Magento 2 URL rewrite redirect, Magento 2 404 handler, Magento 2 page not found redirect

## License

MIT
