<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/photo-functions.php';

function refusePhoto($status)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $status === 404 ? 'Not found' : 'Bad request';
    exit;
}

$invoiceKey = $_GET['id'] ?? '';
$photoId = $_GET['photo'] ?? '';
$size = ($_GET['size'] ?? 'full') === 'thumb' ? 'thumb' : 'full';

if (!preg_match('/^[a-f0-9]{32}$/', $invoiceKey) || !ctype_digit((string) $photoId)) {
    refusePhoto(400);
}

$stmt = $pdo->prepare("
    SELECT p.filename
    FROM invoice_photos p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE p.id = ? AND i.unique_id = ?
");
$stmt->execute([(int) $photoId, $invoiceKey]);
$filename = $stmt->fetchColumn();

if ($filename === false || !preg_match('/^[a-f0-9]{32}\.jpg$/', $filename)) {
    refusePhoto(404);
}

$paths = invoicePhotoPaths($filename);
$path = $paths[$size];

if (!is_file($path)) {
    $path = $paths['full'];
}

if (!is_file($path)) {
    refusePhoto(404);
}

$modified = filemtime($path);
$etag = '"' . md5($filename . $size . $modified) . '"';

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="photo-' . (int) $photoId . '.jpg"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');

$clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
if ($clientEtag !== '' && $clientEtag === $etag) {
    http_response_code(304);
    exit;
}

readfile($path);
