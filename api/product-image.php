<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

$name = $_GET['name'] ?? '';
if (!is_string($name) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $name)) {
    http_response_code(400);
    exit;
}

$path = appImageDirectory() . DIRECTORY_SEPARATOR . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
