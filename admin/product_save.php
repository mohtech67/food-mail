
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function productRedirectError($message, $editId = null)
{
    $_SESSION['product_error'] = $message;

    if ($editId) {
        header('Location: product_edit.php?id=' . (int)$editId);
    } else {
        header('Location: product_add.php');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['admin_csrf'], $csrf)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the form and try again.');
}

$action = $_POST['action'] ?? '';
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$isUpdate = $action === 'update';

if (!in_array($action, ['create', 'update'], true)) {
    productRedirectError('Invalid product operation.');
}

if ($isUpdate && (!$id || $id < 1)) {
    productRedirectError('Invalid product selected.');
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$priceInput = trim($_POST['price'] ?? '');
$stockInput = trim($_POST['stock_quantity'] ?? '');
$isAvailable = isset($_POST['is_available']) ? 1 : 0;

if ($name === '' || strlen($name) > 150) {
    productRedirectError('Enter a valid product name of no more than 150 characters.', $isUpdate ? $id : null);
}

if (strlen($description) > 5000) {
    productRedirectError('The description is too long.', $isUpdate ? $id : null);
}

if (!$categoryId || $categoryId < 1) {
    productRedirectError('Select a valid product category.', $isUpdate ? $id : null);
}

if (!is_numeric($priceInput)) {
    productRedirectError('Enter a valid product price.', $isUpdate ? $id : null);
}

$price = round((float)$priceInput, 2);

if ($price <= 0 || $price > 99999999.99) {
    productRedirectError('The price must be greater than zero and within the permitted limit.', $isUpdate ? $id : null);
}

if ($stockInput === '' || !ctype_digit($stockInput)) {
    productRedirectError('Enter a valid whole-number stock quantity.', $isUpdate ? $id : null);
}

$stock = (int)$stockInput;

if ($stock > 1000000) {
    productRedirectError('The stock quantity is too large.', $isUpdate ? $id : null);
}

/* Verify category */
$categoryStmt = $pdo->prepare("
    SELECT id
    FROM categories
    WHERE id = ? AND is_active = 1
");
$categoryStmt->execute([$categoryId]);

if (!$categoryStmt->fetch()) {
    productRedirectError('The selected category is unavailable. Choose an active category.', $isUpdate ? $id : null);
}

/* Verify existing product */
$oldImage = '';

if ($isUpdate) {
    $existingStmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch();

    if (!$existing) {
        productRedirectError('The product could not be found.');
    }

    $oldImage = $existing['image'] ?? '';
}

$imagePath = $oldImage;
$newImageAbsolutePath = null;

try {

    /* Validate optional uploaded image */
    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            productRedirectError('The image upload failed. Please try again.', $isUpdate ? $id : null);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            productRedirectError('The image must not exceed 5 MB.', $isUpdate ? $id : null);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMime[$mime])) {
            productRedirectError('Only JPG, PNG and WebP images are accepted.', $isUpdate ? $id : null);
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if (!$imageInfo || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
            productRedirectError('The uploaded file is not a valid image.', $isUpdate ? $id : null);
        }

        if ($imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
            productRedirectError('The image dimensions must not exceed 6000 by 6000 pixels.', $isUpdate ? $id : null);
        }

        $uploadDirectory = dirname(__DIR__) . '/uploads/products/';

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException('Unable to create the product image directory.');
        }

        if (!is_writable($uploadDirectory)) {
            throw new RuntimeException('The product image directory is not writable.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];

        $newImageAbsolutePath = $uploadDirectory . $filename;

        if (!move_uploaded_file($file['tmp_name'], $newImageAbsolutePath)) {
            throw new RuntimeException('Unable to save the uploaded product image.');
        }

        $imagePath = 'uploads/products/' . $filename;
    }

    if ($isUpdate) {

        $stmt = $pdo->prepare("
            UPDATE products
            SET
                category_id = :category_id,
                name = :name,
                description = :description,
                price = :price,
                image = :image,
                stock_quantity = :stock_quantity,
                is_available = :is_available,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image' => $imagePath,
            'stock_quantity' => $stock,
            'is_available' => $isAvailable,
            'id' => $id
        ]);

        /* Remove replaced local image only after successful update */
        if (
            $newImageAbsolutePath &&
            $oldImage &&
            strpos($oldImage, 'uploads/products/') === 0
        ) {
            $oldAbsolute = dirname(__DIR__) . '/' . $oldImage;

            if (is_file($oldAbsolute)) {
                @unlink($oldAbsolute);
            }
        }

        $_SESSION['product_success'] = 'Product updated successfully.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO products (
                category_id,
                name,
                description,
                price,
                image,
                stock_quantity,
                is_available
            ) VALUES (
                :category_id,
                :name,
                :description,
                :price,
                :image,
                :stock_quantity,
                :is_available
            )
        ");

        $stmt->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image' => $imagePath,
            'stock_quantity' => $stock,
            'is_available' => $isAvailable
        ]);

        $_SESSION['product_success'] = 'Product added successfully.';
    }

    header('Location: products.php');
    exit;

} catch (Throwable $e) {

    if ($newImageAbsolutePath && is_file($newImageAbsolutePath)) {
        @unlink($newImageAbsolutePath);
    }

    error_log('Food Mail product save error: ' . $e->getMessage());

    productRedirectError('The product could not be saved. Check the server error log or contact the administrator.', $isUpdate ? $id : null);
}
