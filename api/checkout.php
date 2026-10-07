<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/functions.php';

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Only POST requests are allowed.']);
}

$rawBody = file_get_contents('php://input');
$request = $rawBody === false ? null : json_decode($rawBody, true);

if (!is_array($request)) {
    respond(400, ['ok' => false, 'message' => 'The request could not be read.']);
}

$submittedToken = $request['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    respond(403, ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$cart = $request['cart'] ?? [];
if (!is_array($cart)) {
    respond(422, ['ok' => false, 'message' => 'The order is invalid.']);
}

try {
    $products = loadProducts(dirname(__DIR__) . '/data/products.json');
    $order = calculateOrder($cart, $products);

    if ($order['items'] === []) {
        respond(422, ['ok' => false, 'message' => 'Add at least one product before payment.']);
    }

    $payment = validatePayment($request['payment'] ?? null, $order['total_cents']);
    if (!$payment['valid']) {
        respond(422, ['ok' => false, 'message' => $payment['message']]);
    }

    $paidCents = (int) $payment['cents'];
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $transaction = [
        'reference' => createTransactionReference(),
        'created_at' => $now->format(DateTimeInterface::ATOM),
        'items' => $order['items'],
        'total_cents' => $order['total_cents'],
        'paid_cents' => $paidCents,
        'change_cents' => $paidCents - $order['total_cents'],
    ];

    saveTransaction(dirname(__DIR__) . '/data/transactions.json', $transaction);

    respond(200, ['ok' => true, 'transaction' => $transaction]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    respond(500, ['ok' => false, 'message' => 'The transaction could not be saved. Please try again.']);
}
