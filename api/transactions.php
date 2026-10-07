<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

try {
    $transactions = loadRecentTransactions(appDataPath('transactions.json'), 20);
    echo json_encode(['ok' => true, 'transactions' => $transactions], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Transactions could not be loaded.']);
}
