<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function appDataPath(string $filename): string
{
    $customDirectory = getenv('CAMPUS_BAKERY_DATA_DIR');
    $directory = is_string($customDirectory) && trim($customDirectory) !== ''
        ? rtrim($customDirectory, '\\/')
        : dirname(__DIR__) . '/data';

    return $directory . DIRECTORY_SEPARATOR . $filename;
}

function appImageDirectory(): string
{
    $customDirectory = getenv('CAMPUS_BAKERY_IMAGE_DIR');
    return is_string($customDirectory) && trim($customDirectory) !== ''
        ? rtrim($customDirectory, '\\/')
        : dirname(__DIR__) . '/images/products';
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
 * @return array<int, array{id: string, name: string, description: string, price_cents: int, image: ?string}>
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

        $image = $product['image'] ?? null;
        if (!is_string($image) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $image)) {
            $image = null;
        }

        $products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price_cents' => (int) round((float) $product['price'] * 100),
            'image' => $image,
        ];
    }

    return $products;
}

/** @param array<string, mixed> $file */
function storeUploadedProductImage(array $file, string $productId): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'The product image must not exceed 2 MB.'
            : 'The product image could not be uploaded.';
        throw new InvalidArgumentException($message);
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    $size = $file['size'] ?? 0;
    if (!is_string($temporaryPath) || $temporaryPath === '' || !is_numeric($size) || (int) $size < 1 || (int) $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('The product image must be a non-empty file no larger than 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($extensions[$mime]) || getimagesize($temporaryPath) === false) {
        throw new InvalidArgumentException('Choose a valid JPEG, PNG, or WebP image.');
    }

    $directory = appImageDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the product image directory.');
    }

    $safeId = preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower($productId)) ?: 'product';
    $filename = trim($safeId, '-') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Unable to store the product image.');
    }

    return $filename;
}

function deleteProductImage(?string $filename): void
{
    if ($filename === null || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $filename)) {
        return;
    }

    $path = appImageDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * Replace the product database while holding an exclusive file lock.
 *
 * @param callable(array<int, array<string, mixed>>): array<int, array<string, mixed>> $mutator
 * @return array<int, array{id: string, name: string, description: string, price_cents: int}>
 */
function mutateProducts(string $path, callable $mutator): array
{
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the product database.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the product database.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $products = $contents === false ? null : json_decode($contents, true);
        if (!is_array($products)) {
            throw new RuntimeException('The product database is not valid JSON.');
        }

        $updated = $mutator($products);
        $json = json_encode(array_values($updated), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new RuntimeException('Unable to encode the product database.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json . PHP_EOL) === false) {
            throw new RuntimeException('Unable to save the product database.');
        }
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    return loadProducts($path);
}

/** @return array{valid: bool, name?: string, description?: string, price?: float, message?: string} */
function validateProductInput(mixed $name, mixed $description, mixed $price): array
{
    if (!is_string($name) || !is_string($description) || (!is_string($price) && !is_numeric($price))) {
        return ['valid' => false, 'message' => 'Complete all product fields with valid values.'];
    }

    $name = trim($name);
    $description = trim($description);
    $priceText = trim((string) $price);

    if ($name === '' || mb_strlen($name) > 80) {
        return ['valid' => false, 'message' => 'Enter a product name with no more than 80 characters.'];
    }
    if ($description === '' || mb_strlen($description) > 140) {
        return ['valid' => false, 'message' => 'Enter a description with no more than 140 characters.'];
    }
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $priceText)) {
        return ['valid' => false, 'message' => 'Enter a valid product price with up to two decimal places.'];
    }

    $priceValue = (float) $priceText;
    if ($priceValue < 0.01 || $priceValue > 999999.99) {
        return ['valid' => false, 'message' => 'Enter a product price from ₱0.01 to ₱999,999.99.'];
    }

    return [
        'valid' => true,
        'name' => $name,
        'description' => $description,
        'price' => round($priceValue, 2),
    ];
}

/** @param array<int, array<string, mixed>> $products */
function createProductId(string $name, array $products): string
{
    $base = mb_strtolower($name);
    $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'product';
    }

    $existing = array_column($products, 'id');
    $candidate = $base;
    $suffix = 2;
    while (in_array($candidate, $existing, true)) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

/** @return array<int, array<string, mixed>> */
function loadRecentTransactions(string $path, int $limit = 20): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    $transactions = $contents === false ? null : json_decode($contents, true);
    if (!is_array($transactions)) {
        throw new RuntimeException('The transaction database is not valid JSON.');
    }

    return array_slice(array_reverse($transactions), 0, max(1, $limit));
}

/**
 * Rebuild the order using trusted prices from products.json.
 *
 * @param array<mixed> $submittedCart
 * @param array<int, array{id: string, name: string, description: string, price_cents: int, image: ?string}> $products
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
