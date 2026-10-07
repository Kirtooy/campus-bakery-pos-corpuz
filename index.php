<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = loadProducts(appDataPath('products.json'));
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
        <div class="header-tools">
            <nav class="header-actions" aria-label="Point of sale tools">
                <button id="view-transactions" class="header-button" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>
                    Transactions
                </button>
                <button id="manage-products" class="header-button" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 4v6m10-6v6M6 12h12v8H6z"/></svg>
                    Manage products
                </button>
            </nav>
            <div class="header-meta">
                <span class="status-dot" aria-hidden="true"></span>
                Counter open
                <span class="header-divider" aria-hidden="true"></span>
                <?= escape($today) ?>
            </div>
        </div>
    </header>

    <main class="pos-layout">
        <section class="catalog" aria-labelledby="products-heading">
            <div class="section-heading">
                <div>
                    <h1 id="products-heading">Fresh from the bakery</h1>
                    <p>Select an item to add it to the current order.</p>
                </div>
                <span id="product-count" class="product-count"><?= count($products) ?> products</span>
            </div>

            <div id="product-grid" class="product-grid">
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <div class="product-visual" aria-hidden="true">
                            <?php if ($product['image'] !== null): ?>
                                <img src="api/product-image.php?name=<?= rawurlencode($product['image']) ?>" alt="">
                            <?php else: ?>
                                <span><?= escape(productInitials($product['name'])) ?></span>
                            <?php endif; ?>
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

    <dialog id="transactions-dialog" class="management-dialog history-dialog" aria-labelledby="transactions-title">
        <div class="dialog-heading">
            <div>
                <p>Sales record</p>
                <h2 id="transactions-title">Recent transactions</h2>
            </div>
            <button class="dialog-close" type="button" data-close-dialog="transactions-dialog" aria-label="Close recent transactions">×</button>
        </div>
        <p class="dialog-description">The 20 most recent completed sales, newest first.</p>
        <div id="transactions-feedback" class="dialog-feedback" role="status" aria-live="polite"></div>
        <div id="transactions-list" class="transactions-list"></div>
    </dialog>

    <dialog id="products-dialog" class="management-dialog products-dialog" aria-labelledby="products-dialog-title">
        <div class="dialog-heading">
            <div>
                <p>Catalog tools</p>
                <h2 id="products-dialog-title">Manage products</h2>
            </div>
            <button class="dialog-close" type="button" data-close-dialog="products-dialog" aria-label="Close product management">×</button>
        </div>
        <p class="dialog-description">Add bakery items or update the products shown at the counter.</p>
        <div id="products-feedback" class="dialog-feedback" role="status" aria-live="polite"></div>

        <div class="product-manager">
            <section aria-labelledby="catalog-list-title">
                <div class="manager-section-heading">
                    <h3 id="catalog-list-title">Current catalog</h3>
                    <span id="manager-product-count"><?= count($products) ?> items</span>
                </div>
                <div id="product-admin-list" class="product-admin-list"></div>
            </section>

            <section class="product-form-panel" aria-labelledby="product-form-title">
                <h3 id="product-form-title">Add product</h3>
                <form id="product-form" novalidate>
                    <input id="product-id" name="id" type="hidden">

                    <label for="product-name">Product name</label>
                    <input id="product-name" name="name" type="text" maxlength="80" required>

                    <label for="product-description">Short description</label>
                    <textarea id="product-description" name="description" maxlength="140" rows="3" required></textarea>

                    <label for="product-price">Price in pesos</label>
                    <div class="manager-money-input">
                        <span aria-hidden="true">₱</span>
                        <input id="product-price" name="price" type="number" min="0.01" max="999999.99" step="0.01" placeholder="0.00" required>
                    </div>

                    <label for="product-image">Product image <span class="optional-label">Optional</span></label>
                    <input id="product-image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="field-hint">JPEG, PNG, or WebP. Maximum 2 MB.</p>
                    <div id="product-image-preview" class="product-image-preview">
                        <img id="product-preview-image" alt="Selected product preview" hidden>
                        <span id="product-preview-empty">No image selected</span>
                    </div>
                    <button id="remove-product-image" class="remove-image-button" type="button" hidden>Remove current image</button>

                    <p id="product-form-error" class="field-error" role="alert"></p>
                    <div class="product-form-actions">
                        <button id="cancel-product-edit" class="secondary-button" type="button" hidden>Cancel edit</button>
                        <button id="save-product" class="pay-button" type="submit">Add product</button>
                    </div>
                </form>
            </section>
        </div>
    </dialog>

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
