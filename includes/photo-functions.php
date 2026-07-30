<?php

function invoicePhotoDir()
{
    return dirname(__DIR__) . '/uploads/invoice-photos';
}

function invoicePhotoLimits()
{
    return [
        'max_bytes' => 15 * 1024 * 1024,
        'max_edge' => 1600,
        'thumb_edge' => 500,
        'quality' => 82,
        'max_per_invoice' => 24,
    ];
}

function ensureInvoicePhotoDir()
{
    $dir = invoicePhotoDir();

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Photo storage is not available.');
    }

    $guard = $dir . '/.htaccess';
    if (!file_exists($guard)) {
        file_put_contents($guard, "Require all denied\n");
    }

    return $dir;
}

function getInvoicePhotos($pdo, $invoiceId)
{
    $stmt = $pdo->prepare("
        SELECT id, invoice_id, filename, original_name, caption, file_size, created_at
        FROM invoice_photos
        WHERE invoice_id = ?
        ORDER BY id
    ");
    $stmt->execute([(int) $invoiceId]);

    return $stmt->fetchAll();
}

function countInvoicePhotos($pdo, $invoiceId)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_photos WHERE invoice_id = ?");
    $stmt->execute([(int) $invoiceId]);

    return (int) $stmt->fetchColumn();
}

function invoicePhotoPaths($filename)
{
    $dir = invoicePhotoDir();

    return [
        'full' => $dir . '/' . $filename,
        'thumb' => $dir . '/thumb-' . $filename,
    ];
}

function photoUploadErrorMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That photo is too large. Please keep each photo under 15 MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'That photo only uploaded part way. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'The photo could not be saved. Please try again.';
        case UPLOAD_ERR_EXTENSION:
            return 'That photo was rejected by the server.';
        default:
            return 'That photo could not be uploaded.';
    }
}

function loadUploadedImage($path, $type)
{
    switch ($type) {
        case IMAGETYPE_JPEG:
            return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            return @imagecreatefrompng($path);
        case IMAGETYPE_WEBP:
            return @imagecreatefromwebp($path);
        default:
            return false;
    }
}

function applyPhotoOrientation($image, $path, $type)
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($path);
    $orientation = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

    $rotate = 0;
    $flip = null;

    switch ($orientation) {
        case 2:
            $flip = IMG_FLIP_HORIZONTAL;
            break;
        case 3:
            $rotate = 180;
            break;
        case 4:
            $flip = IMG_FLIP_VERTICAL;
            break;
        case 5:
            $rotate = -90;
            $flip = IMG_FLIP_HORIZONTAL;
            break;
        case 6:
            $rotate = -90;
            break;
        case 7:
            $rotate = 90;
            $flip = IMG_FLIP_HORIZONTAL;
            break;
        case 8:
            $rotate = 90;
            break;
    }

    if ($rotate !== 0) {
        $rotated = imagerotate($image, $rotate, 0);
        if ($rotated) {
            imagedestroy($image);
            $image = $rotated;
        }
    }

    if ($flip !== null) {
        imageflip($image, $flip);
    }

    return $image;
}

function writeResizedJpeg($image, $path, $maxEdge, $quality)
{
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, $maxEdge / max($width, $height));

    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));

    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $written = imagejpeg($canvas, $path, $quality);
    imagedestroy($canvas);

    return $written;
}

function storeInvoicePhoto($pdo, $invoiceId, array $file, $caption = null)
{
    $limits = invoicePhotoLimits();

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new InvalidArgumentException('That upload was not valid.');
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(photoUploadErrorMessage($file['error']));
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('That upload was not valid.');
    }

    if ($file['size'] <= 0 || $file['size'] > $limits['max_bytes']) {
        throw new InvalidArgumentException('Each photo must be under 15 MB.');
    }

    if (countInvoicePhotos($pdo, $invoiceId) >= $limits['max_per_invoice']) {
        throw new InvalidArgumentException('This invoice already has ' . $limits['max_per_invoice'] . ' photos.');
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset($info[2])) {
        throw new InvalidArgumentException('That file is not a photo. Please upload a JPG or PNG.');
    }

    $type = (int) $info[2];
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new InvalidArgumentException('Photos must be JPG, PNG, or WebP.');
    }

    $image = loadUploadedImage($file['tmp_name'], $type);
    if (!$image) {
        throw new InvalidArgumentException('That photo could not be read.');
    }

    $dir = ensureInvoicePhotoDir();
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $paths = invoicePhotoPaths($filename);

    try {
        $image = applyPhotoOrientation($image, $file['tmp_name'], $type);

        if (!writeResizedJpeg($image, $paths['full'], $limits['max_edge'], $limits['quality'])) {
            throw new RuntimeException('Could not write the photo.');
        }

        if (!writeResizedJpeg($image, $paths['thumb'], $limits['thumb_edge'], $limits['quality'])) {
            throw new RuntimeException('Could not write the photo preview.');
        }
    } catch (Exception $e) {
        imagedestroy($image);
        @unlink($paths['full']);
        @unlink($paths['thumb']);
        throw $e;
    }

    imagedestroy($image);

    $originalName = mb_substr(basename((string) ($file['name'] ?? '')), 0, 191);
    $caption = $caption === null ? null : mb_substr(trim(preg_replace('/\s+/', ' ', (string) $caption)), 0, 191);
    if ($caption === '') {
        $caption = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO invoice_photos (invoice_id, filename, original_name, caption, file_size)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([(int) $invoiceId, $filename, $originalName, $caption, filesize($paths['full'])]);

    unset($dir);

    return (int) $pdo->lastInsertId();
}

function storeInvoicePhotos($pdo, $invoiceId, array $files)
{
    $result = ['stored' => 0, 'errors' => []];

    if (!isset($files['error']) || !is_array($files['error'])) {
        return $result;
    }

    foreach (array_keys($files['error']) as $index) {
        $file = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];

        try {
            if (storeInvoicePhoto($pdo, $invoiceId, $file) !== null) {
                $result['stored']++;
            }
        } catch (InvalidArgumentException $e) {
            $result['errors'][] = $e->getMessage();
        } catch (Exception $e) {
            logSecureError('Invoice photo upload failed', ['error' => $e->getMessage()]);
            $result['errors'][] = 'One photo could not be saved.';
        }
    }

    $result['errors'] = array_values(array_unique($result['errors']));

    return $result;
}

function updateInvoicePhotoCaption($pdo, $photoId, $invoiceId, $caption)
{
    $caption = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $caption)), 0, 191);

    $stmt = $pdo->prepare("UPDATE invoice_photos SET caption = ? WHERE id = ? AND invoice_id = ?");
    $stmt->execute([$caption === '' ? null : $caption, (int) $photoId, (int) $invoiceId]);

    return $stmt->rowCount() > 0;
}

function deleteInvoicePhoto($pdo, $photoId, $invoiceId)
{
    $stmt = $pdo->prepare("SELECT filename FROM invoice_photos WHERE id = ? AND invoice_id = ?");
    $stmt->execute([(int) $photoId, (int) $invoiceId]);
    $filename = $stmt->fetchColumn();

    if ($filename === false) {
        return false;
    }

    $stmt = $pdo->prepare("DELETE FROM invoice_photos WHERE id = ? AND invoice_id = ?");
    $stmt->execute([(int) $photoId, (int) $invoiceId]);

    $paths = invoicePhotoPaths($filename);
    @unlink($paths['full']);
    @unlink($paths['thumb']);

    return true;
}
