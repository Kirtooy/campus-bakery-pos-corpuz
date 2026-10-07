# Campus Bakery POS

A desktop-friendly, browser-based point-of-sale system for a campus bakery. It uses vanilla PHP, CSS, JavaScript, and JSON files—no database server or external packages are required.

## Features

- Six bakery products loaded from `data/products.json`
- Add, increase, decrease, and remove cart items
- Automatic subtotals and order total
- Blank, non-numeric, negative, and insufficient-payment validation
- Server-verified prices, totals, payment, and change
- Payment confirmation and digital receipt modal
- Unique transaction reference for every completed sale
- Transactions saved to `data/transactions.json`
- New Transaction action that clears the cart, payment, and receipt
- CSRF protection for checkout requests

## Run the application

From the project folder, start PHP's local server:

```powershell
php -S 127.0.0.1:8000
```

Open <http://127.0.0.1:8000> in Chrome.

## Run the tests

```powershell
php tests/functions_test.php
```

With Playwright available, the browser flow can also be checked using:

```powershell
node tests/browser_check.cjs http://127.0.0.1:8000
```

You can also check every PHP file for syntax errors:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## JSON storage

- `data/products.json` contains the product catalog and prices.
- `data/transactions.json` stores completed transactions.

The server recalculates the order from the trusted product file instead of trusting totals sent by the browser.

## Adding product images later

The `images` folder contains suggested filenames. The current version uses readable text initials and does not depend on images, so it remains fully usable offline.

## Suggested Git milestones

Use your own accurate commit messages. The exam requires at least these milestones:

1. Project setup and basic interface
2. Core cart, total, and change-computation functionality
3. Payment validation, testing, and final improvements

## Required evidence checklist

- Product list screenshot
- Cart/order summary screenshot with quantities, subtotals, and total
- Successful payment screenshot showing computed change
- Receipt screenshot with transaction reference
- GitHub commit-history screenshot
- At least two genuine AI prompt records completed in the supplied documentation form
