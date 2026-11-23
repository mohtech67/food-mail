
<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

function categoryError($message, $id = null)
{
    $_SESSION['category_error'] = $message;

    if ($id) {
        header('Location: category_form.php?id=' . (int)$id);
    } else {
        header('Location: category_form.php');
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: categories.php');
    exit;
}

// CSRF validation.
$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['admin_csrf'], $csrf)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

$action = $_POST['action'] ?? '';
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$isUpdate = $action === 'update';

if (!in_array($action, ['create', 'update'], true)) {
    categoryError('Invalid category operation.');
}

if ($isUpdate && (!$id || $id < 1)) {
    categoryError('Invalid category selected.');
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '' || strlen($name) > 100) {
    categoryError(
        'Enter a valid category name of no more than 100 characters.',
        $isUpdate ? $id : null
    );
}

if (strlen($description) > 1000) {
    categoryError(
        'The category description must not exceed 1,000 characters.',
        $isUpdate ? $id : null
    );
}

try {

    if ($isUpdate) {
        $check = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        $check->execute([$id]);

        if (!$check->fetch()) {
            categoryError('The category does not exist.');
        }
    }

    // Prevent duplicate category names.
    $duplicate = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE name = ?
        AND (:current_id IS NULL OR id != :excluded_id)
        LIMIT 1
    ");

    $currentId = $isUpdate ? $id : null;

    $duplicate->execute([
        'current_id' => $currentId,
        'excluded_id' => $currentId ?? 0
    ]);

    if ($duplicate->fetch()) {
        categoryError(
            'A category with this name already exists.',
            $isUpdate ? $id : null
        );
    }

    if ($isUpdate) {

        $stmt = $pdo->prepare("
            UPDATE categories
            SET
                name = :name,
                description = :description,
                is_active = :is_active
            WHERE id = :id
        ");

        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
            'id' => $id
        ]);

        $_SESSION['category_success'] = 'Category updated successfully.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO categories (
                name,
                description,
                is_active
            )
            VALUES (
                :name,
                :description,
                :is_active
            )
        ");

        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive
        ]);

        $_SESSION['category_success'] = 'Category created successfully.';
    }

    header('Location: categories.php');
    exit;

} catch (PDOException $e) {

    error_log('Food Mail category save error: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        categoryError(
            'This category name already exists.',
            $isUpdate ? $id : null
        );
    }

    categoryError(
        'Unable to save the category. Please check the database and try again.',
        $isUpdate ? $id : null
    );
}
