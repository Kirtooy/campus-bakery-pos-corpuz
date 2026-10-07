<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/functions.php';

function productResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$path = appDataPath('products.json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        productResponse(200, ['ok' => true, 'products' => loadProducts($path)]);
    } catch (Throwable $error) {
        error_log($error->getMessage());
        productResponse(500, ['ok' => false, 'message' => 'Products could not be loaded.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    productResponse(405, ['ok' => false, 'message' => 'Only GET and POST requests are allowed.']);
}

$rawBody = file_get_contents('php://input');
$request = $rawBody === false ? null : json_decode($rawBody, true);
if (!is_array($request)) {
    productResponse(400, ['ok' => false, 'message' => 'The request could not be read.']);
}

$submittedToken = $request['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    productResponse(403, ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$action = $request['action'] ?? '';
if (!in_array($action, ['add', 'update', 'delete'], true)) {
    productResponse(422, ['ok' => false, 'message' => 'Choose a valid product action.']);
}

try {
    $updatedProducts = mutateProducts($path, function (array $products) use ($action, $request): array {
        if ($action === 'delete') {
            $id = $request['id'] ?? '';
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Choose a product to remove.');
            }
            if (count($products) <= 5) {
                throw new InvalidArgumentException('At least five products are required in the catalog.');
            }

            $before = count($products);
            $products = array_values(array_filter($products, fn (array $product): bool => ($product['id'] ?? '') !== $id));
            if (count($products) === $before) {
                throw new InvalidArgumentException('The selected product was not found.');
            }
            return $products;
        }

        $validated = validateProductInput($request['name'] ?? null, $request['description'] ?? null, $request['price'] ?? null);
        if (!$validated['valid']) {
            throw new InvalidArgumentException($validated['message']);
        }

        if ($action === 'add') {
            $products[] = [
                'id' => createProductId($validated['name'], $products),
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
            ];
            return $products;
        }

        $id = $request['id'] ?? '';
        if (!is_string($id) || $id === '') {
            throw new InvalidArgumentException('Choose a product to update.');
        }

        $found = false;
        foreach ($products as &$product) {
            if (($product['id'] ?? '') === $id) {
                $product['name'] = $validated['name'];
                $product['description'] = $validated['description'];
                $product['price'] = $validated['price'];
                $found = true;
                break;
            }
        }
        unset($product);

        if (!$found) {
            throw new InvalidArgumentException('The selected product was not found.');
        }
        return $products;
    });

    productResponse(200, [
        'ok' => true,
        'message' => $action === 'add' ? 'Product added.' : ($action === 'update' ? 'Product updated.' : 'Product removed.'),
        'products' => $updatedProducts,
    ]);
} catch (InvalidArgumentException $error) {
    productResponse(422, ['ok' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    productResponse(500, ['ok' => false, 'message' => 'The product catalog could not be updated.']);
}
