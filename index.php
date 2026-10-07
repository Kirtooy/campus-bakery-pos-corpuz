<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = loadProducts(__DIR__ . '/data/products.json');
$csrfToken = $_SESSION['csrf_token'];
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('F j, Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Campus Bakery point-of-sale system">
    <title>Campus Bakery POS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Campus Bakery home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" role="img">
                    <path d="M9 24h14M10 20c-3-1-4-4-3-7 1-4 5-6 9-6s8 2 9 6c1 3 0 6-3 7M11 20h10l1 6H10l1-6Z"/>
                    <path d="M12 13c1-2 3-3 4-3m1 0c2 0 3 1 4 3"/>
                </svg>
            </span>
            <span>
                <strong>Campus Bakery</strong>
                <small>Point of sale</small>
            </span>
        </a>
        <div class="header-meta">
            <span class="status-dot" aria-hidden="true"></span>
            Counter open
            <span class="header-divider" aria-hidden="true"></span>
            <?= escape($today) ?>
        </div>
    </header>

    <main class="pos-layout">
        <section class="catalog" aria-labelledby="products-heading">
            <div class="section-heading">
                <div>
                    <h1 id="products-heading">Fresh from the bakery</h1>
                    <p>Select an item to add it to the current order.</p>
                </div>
                <span class="product-count"><?= count($products) ?> products</span>
            </div>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <div class="product-visual" aria-hidden="true">
                            <span><?= escape(productInitials($product['name'])) ?></span>
                        </div>
                        <div class="product-copy">
                            <h2><?= escape($product['name']) ?></h2>
                            <p><?= escape($product['description']) ?></p>
                        </div>
                        <div class="product-action">
                            <strong><?= formatPeso((int) $product['price_cents']) ?></strong>
                            <button
                                class="add-button"
                                type="button"
                                data-add-product="<?= escape($product['id']) ?>"
                                aria-label="Add <?= escape($product['name']) ?> to order"
                            >
                                <span aria-hidden="true">+</span> Add
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="order-panel" aria-labelledby="order-heading">
            <div class="order-heading">
                <div>
                    <p class="order-number">Current order</p>
                    <h2 id="order-heading">Order summary</h2>
                </div>
                <button id="clear-order" class="text-button" type="button" disabled>Clear</button>
            </div>

            <div id="feedback" class="feedback" role="status" aria-live="polite"></div>

            <div id="cart-empty" class="empty-cart">
                <span class="empty-cart-icon" aria-hidden="true">
                    <svg viewBox="0 0 32 32"><path d="M7 9h2l2 12h11l3-9H10m3 14a1.5 1.5 0 1 0 0 .01M22 26a1.5 1.5 0 1 0 0 .01"/></svg>
                </span>
                <h3>Your order is empty</h3>
                <p>Add a bakery item to begin.</p>
            </div>

            <div id="cart-items" class="cart-items" aria-live="polite"></div>

            <div class="payment-area">
                <div class="total-row">
                    <span>Total amount</span>
                    <strong id="cart-total">₱0.00</strong>
                </div>

                <form id="payment-form" novalidate>
                    <label for="cash-payment">Cash payment</label>
                    <div class="money-input">
                        <span aria-hidden="true">₱</span>
                        <input
                            id="cash-payment"
                            name="payment"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            autocomplete="off"
                            disabled
                        >
                    </div>
                    <p id="payment-error" class="field-error" role="alert"></p>
                    <button id="pay-button" class="pay-button" type="submit" disabled>
                        Complete payment
                    </button>
                </form>
            </div>
        </aside>
    </main>

    <dialog id="receipt-dialog" class="receipt-dialog" aria-labelledby="receipt-title">
        <div class="success-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32"><path d="m9 16 5 5 10-11"/></svg>
        </div>
        <p class="payment-confirmation">Payment successful</p>
        <h2 id="receipt-title">Digital receipt</h2>
        <p id="receipt-reference" class="receipt-reference"></p>

        <div class="receipt-rule"></div>
        <div id="receipt-items" class="receipt-items"></div>
        <div class="receipt-rule"></div>

        <dl class="receipt-totals">
            <div><dt>Total</dt><dd id="receipt-total"></dd></div>
            <div><dt>Cash received</dt><dd id="receipt-paid"></dd></div>
            <div class="receipt-change"><dt>Change</dt><dd id="receipt-change"></dd></div>
        </dl>

        <p class="receipt-thanks">Thank you for visiting Campus Bakery.</p>
        <button id="new-transaction" class="pay-button" type="button">Start new transaction</button>
    </dialog>

    <script>
        window.CAMPUS_BAKERY = <?= json_encode([
            'products' => $products,
            'csrfToken' => $csrfToken,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>
