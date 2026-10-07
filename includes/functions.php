<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatPeso(int $cents): string
{
    return '₱' . number_format($cents / 100, 2);
}

function productInitials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }

    return mb_strtoupper($initials ?: 'CB');
}

/**
 * @return array<int, array{id: string, name: string, description: string, price_cents: int}>
 */
function loadProducts(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('The product database is missing.');
    }

    $contents = file_get_contents($path);
    $decoded = $contents === false ? null : json_decode($contents, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('The product database is not valid JSON.');
    }

    $products = [];
    foreach ($decoded as $product) {
        if (
            !is_array($product)
            || !isset($product['id'], $product['name'], $product['description'], $product['price'])
            || !is_string($product['id'])
            || !is_string($product['name'])
            || !is_string($product['description'])
            || !is_numeric($product['price'])
        ) {
            throw new RuntimeException('A product record is incomplete.');
        }

        $products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price_cents' => (int) round((float) $product['price'] * 100),
        ];
    }

    return $products;
}

/**
 * Rebuild the order using trusted prices from products.json.
 *
 * @param array<mixed> $submittedCart
 * @param array<int, array{id: string, name: string, description: string, price_cents: int}> $products
 * @return array{items: array<int, array{id: string, name: string, quantity: int, unit_price_cents: int, subtotal_cents: int}>, total_cents: int}
 */
function calculateOrder(array $submittedCart, array $products): array
{
    $productMap = [];
    foreach ($products as $product) {
        $productMap[$product['id']] = $product;
    }

    $items = [];
    $totalCents = 0;

    foreach ($submittedCart as $line) {
        if (!is_array($line) || !isset($line['id'], $line['quantity'])) {
            continue;
        }

        $id = (string) $line['id'];
        $quantity = filter_var($line['quantity'], FILTER_VALIDATE_INT);

        if (!isset($productMap[$id]) || $quantity === false || $quantity < 1 || $quantity > 999) {
            continue;
        }

        $product = $productMap[$id];
        $subtotalCents = $product['price_cents'] * $quantity;
        $totalCents += $subtotalCents;
        $items[] = [
            'id' => $id,
            'name' => $product['name'],
            'quantity' => $quantity,
            'unit_price_cents' => $product['price_cents'],
            'subtotal_cents' => $subtotalCents,
        ];
    }

    return ['items' => $items, 'total_cents' => $totalCents];
}

/**
 * @return array{valid: bool, cents?: int, message?: string}
 */
function validatePayment(mixed $payment, int $totalCents): array
{
    if (!is_string($payment)) {
        return ['valid' => false, 'message' => 'Please enter a valid payment amount.'];
    }

    $payment = trim($payment);
    if ($payment === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $payment)) {
        return ['valid' => false, 'message' => 'Please enter a valid payment amount.'];
    }

    [$whole, $decimal] = array_pad(explode('.', $payment, 2), 2, '');
    $decimal = str_pad($decimal, 2, '0');
    $paymentCents = ((int) $whole * 100) + (int) $decimal;

    if ($paymentCents < $totalCents) {
        return [
            'valid' => false,
            'message' => 'Insufficient payment. Please enter at least ' . formatPeso($totalCents) . '.',
        ];
    }

    return ['valid' => true, 'cents' => $paymentCents];
}

/** @param array<string, mixed> $transaction */
function saveTransaction(string $path, array $transaction): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the data directory.');
    }

    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the transaction database.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the transaction database.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $transactions = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true);
        if (!is_array($transactions)) {
            $transactions = [];
        }

        $transactions[] = $transaction;
        $json = json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode the transaction.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json . PHP_EOL) === false) {
            throw new RuntimeException('Unable to save the transaction.');
        }

        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function createTransactionReference(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    return 'CB-' . $now->format('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}
