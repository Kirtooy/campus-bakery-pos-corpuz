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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$uploadedFile = null;
if (str_starts_with($contentType, 'multipart/form-data')) {
    $request = $_POST;
    if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadedFile = $_FILES['image'];
    }
} else {
    $rawBody = file_get_contents('php://input');
    $request = $rawBody === false ? null : json_decode($rawBody, true);
}

if (!is_array($request)) {
    productResponse(400, ['ok' => false, 'message' => 'The request could not be read.']);
}

$submittedToken = $request['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    productResponse(403, ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$action = $request['action'] ?? '';
if (!in_array($action, ['add', 'update', 'delete', 'remove_image'], true)) {
    productResponse(422, ['ok' => false, 'message' => 'Choose a valid product action.']);
}

$newImage = null;
$oldImage = null;

try {
    $updatedProducts = mutateProducts($path, function (array $products) use ($action, $request, $uploadedFile, &$newImage, &$oldImage): array {
        $id = $request['id'] ?? '';

        if ($action === 'delete') {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Choose a product to remove.');
            }
            if (count($products) <= 5) {
                throw new InvalidArgumentException('At least five products are required in the catalog.');
            }

            $found = false;
            foreach ($products as $index => $product) {
                if (($product['id'] ?? '') === $id) {
                    $oldImage = is_string($product['image'] ?? null) ? $product['image'] : null;
                    unset($products[$index]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new InvalidArgumentException('The selected product was not found.');
            }
            return array_values($products);
        }

        if ($action === 'remove_image') {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Choose a product image to remove.');
            }
            foreach ($products as &$product) {
                if (($product['id'] ?? '') === $id) {
                    $oldImage = is_string($product['image'] ?? null) ? $product['image'] : null;
                    $product['image'] = null;
                    return $products;
                }
            }
            unset($product);
            throw new InvalidArgumentException('The selected product was not found.');
        }

        $validated = validateProductInput($request['name'] ?? null, $request['description'] ?? null, $request['price'] ?? null);
        if (!$validated['valid']) {
            throw new InvalidArgumentException($validated['message']);
        }

        if ($action === 'add') {
            $id = createProductId($validated['name'], $products);
            if ($uploadedFile !== null) {
                $newImage = storeUploadedProductImage($uploadedFile, $id);
            }
            $products[] = [
                'id' => $id,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'image' => $newImage,
            ];
            return $products;
        }

        if (!is_string($id) || $id === '') {
            throw new InvalidArgumentException('Choose a product to update.');
        }

        foreach ($products as &$product) {
            if (($product['id'] ?? '') === $id) {
                $product['name'] = $validated['name'];
                $product['description'] = $validated['description'];
                $product['price'] = $validated['price'];
                if ($uploadedFile !== null) {
                    $oldImage = is_string($product['image'] ?? null) ? $product['image'] : null;
                    $newImage = storeUploadedProductImage($uploadedFile, $id);
                    $product['image'] = $newImage;
                }
                return $products;
            }
        }
        unset($product);
        throw new InvalidArgumentException('The selected product was not found.');
    });

    if ($oldImage !== null && $oldImage !== $newImage) {
        deleteProductImage($oldImage);
    }

    $messages = [
        'add' => 'Product added.',
        'update' => 'Product updated.',
        'delete' => 'Product removed.',
        'remove_image' => 'Product image removed.',
    ];
    productResponse(200, [
        'ok' => true,
        'message' => $messages[$action],
        'products' => $updatedProducts,
    ]);
} catch (InvalidArgumentException $error) {
    if ($newImage !== null) {
        deleteProductImage($newImage);
    }
    productResponse(422, ['ok' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    if ($newImage !== null) {
        deleteProductImage($newImage);
    }
    error_log($error->getMessage());
    productResponse(500, ['ok' => false, 'message' => 'The product catalog could not be updated.']);
}
