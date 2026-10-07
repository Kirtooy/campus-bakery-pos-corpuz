<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

$products = loadProducts(dirname(__DIR__) . '/data/products.json');
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$order = calculateOrder([
    ['id' => 'pandesal', 'quantity' => 2],
    ['id' => 'loaf-bread', 'quantity' => 1],
    ['id' => 'unknown', 'quantity' => 50],
], $products);

check(count($products) === 6, 'Expected all six products.');
check(count($order['items']) === 2, 'Unknown products must be rejected.');
check($order['total_cents'] === 10500, 'Order total should be ₱105.00.');
check(validatePayment('', 10500)['valid'] === false, 'Blank payment should fail.');
check(validatePayment('hello', 10500)['valid'] === false, 'Non-numeric payment should fail.');
check(validatePayment('-1', 10500)['valid'] === false, 'Negative payment should fail.');
check(validatePayment('100', 10500)['valid'] === false, 'Insufficient payment should fail.');
check(validatePayment('105', 10500)['valid'] === true, 'Exact payment should pass.');
check(validatePayment('200.00', 10500)['cents'] === 20000, 'Payment should convert to cents.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "All function tests passed." . PHP_EOL;
