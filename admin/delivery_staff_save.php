
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function staffSaveRedirect(
    string $message,
    string $location = 'delivery_staff.php'
): void {
    $_SESSION['delivery_staff_error'] = $message;
    header('Location: ' . $location);
    exit;
}

function staffValidFilename(?string $filename): ?string
{
    if (
        !is_string($filename) ||
        $filename === '' ||
        basename($filename) !== $filename
    ) {
        return null;
    }

    return $filename;
}

function staffDeleteImage(?string $filename): void
{
    $filename = staffValidFilename($filename);

    if ($filename === null) {
        return;
    }

    $path = __DIR__ . '/../uploads/delivery_staff/' . $filename;

    if (is_file($path)) {
        @unlink($path);
    }
}

/* ------------------------------------------------------------
   REQUEST VALIDATION
------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($token) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh and try again.');
}

/* ------------------------------------------------------------
   READ FORM DATA
------------------------------------------------------------ */

$id = filter_var(
    $_POST['staff_id'] ?? '',
    FILTER_VALIDATE_INT
);

$id = ($id && $id > 0) ? $id : null;

$name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$status = $_POST['account_status'] ?? 'active';

$formLocation = $id
    ? 'delivery_staff_form.php?id=' . $id
    : 'delivery_staff_form.php';

if (
    $name === '' ||
    strlen($name) > 150 ||
    $phone === '' ||
    strlen($phone) > 20 ||
    !preg_match('/^[0-9+\s()-]{7,20}$/', $phone)
) {
    staffSaveRedirect(
        'Please provide a valid full name and phone number.',
        $formLocation
    );
}

if ($email !== '') {

    if (
        strlen($email) > 190 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        staffSaveRedirect(
            'Please enter a valid email address.',
            $formLocation
        );
    }

} else {
    $email = null;
}

if (!in_array($status, ['active', 'suspended'], true)) {
    staffSaveRedirect('Invalid account status.', $formLocation);
}

if (!is_string($password)) {
    staffSaveRedirect('Invalid password.', $formLocation);
}

if ($password !== '' && strlen($password) < 12) {
    staffSaveRedirect(
        'The password must contain at least 12 characters.',
        $formLocation
    );
}

if (!$id && $password === '') {
    staffSaveRedirect(
        'A login password is required when creating a new staff account.',
        $formLocation
    );
}

/* ------------------------------------------------------------
   UPLOAD DIRECTORY
------------------------------------------------------------ */

$uploadDirectory = __DIR__ . '/../uploads/delivery_staff/';

if (!is_dir($uploadDirectory)) {

    if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        staffSaveRedirect(
            'Unable to create the profile image upload directory.',
            $formLocation
        );
    }
}

if (!is_writable($uploadDirectory)) {
    staffSaveRedirect(
        'The profile image upload directory is not writable.',
        $formLocation
    );
}

/* ------------------------------------------------------------
   PROFILE IMAGE VALIDATION AND UPLOAD
------------------------------------------------------------ */

$newImageFilename = null;
$oldImageFilename = null;

$file = $_FILES['profile_image'] ?? null;

if ($file !== null) {

    if (!is_array($file) || !isset($file['error'])) {
        staffSaveRedirect('Invalid profile image upload.', $formLocation);
    }

    if ($file['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($file['error'] !== UPLOAD_ERR_OK) {

            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE =>
                    'The image exceeds the server upload size limit.',

                UPLOAD_ERR_FORM_SIZE =>
                    'The image exceeds the allowed upload size.',

                UPLOAD_ERR_PARTIAL =>
                    'The image was only partially uploaded.',

                UPLOAD_ERR_NO_TMP_DIR =>
                    'The server temporary upload directory is missing.',

                UPLOAD_ERR_CANT_WRITE =>
                    'The server could not save the uploaded image.',

                UPLOAD_ERR_EXTENSION =>
                    'The server rejected the image upload.'
            ];

            $message = $uploadErrors[$file['error']]
                ?? 'The profile image upload failed.';

            staffSaveRedirect($message, $formLocation);
        }

        if (
            empty($file['tmp_name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            staffSaveRedirect(
                'The uploaded profile image is invalid.',
                $formLocation
            );
        }

        if (
            (int)$file['size'] < 1 ||
            (int)$file['size'] > 3 * 1024 * 1024
        ) {
            staffSaveRedirect(
                'The profile image must not exceed 3 MB.',
                $formLocation
            );
        }

        if (!class_exists('finfo')) {
            staffSaveRedirect(
                'PHP Fileinfo is not enabled. Enable the fileinfo extension in XAMPP.',
                $formLocation
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedTypes[$mimeType])) {
            staffSaveRedirect(
                'Only JPG, JPEG, PNG and WEBP profile images are allowed.',
                $formLocation
            );
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false) {
            staffSaveRedirect(
                'The uploaded file is not a valid image.',
                $formLocation
            );
        }

        if (($imageInfo['mime'] ?? '') !== $mimeType) {
            staffSaveRedirect(
                'The uploaded image format could not be verified.',
                $formLocation
            );
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];

        if (
            $width < 50 ||
            $height < 50 ||
            $width > 5000 ||
            $height > 5000
        ) {
            staffSaveRedirect(
                'Image dimensions must be between 50 and 5000 pixels.',
                $formLocation
            );
        }

        $newImageFilename =
            bin2hex(random_bytes(20)) .
            '.' .
            $allowedTypes[$mimeType];

        $destination = $uploadDirectory . $newImageFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $newImageFilename = null;

            staffSaveRedirect(
                'Unable to save the profile photograph.',
                $formLocation
            );
        }

        @chmod($destination, 0644);
    }
}

/* ------------------------------------------------------------
   DATABASE OPERATIONS
------------------------------------------------------------ */

try {

    // Retrieve existing staff details when editing.
    if ($id) {

        $existingStmt = $pdo->prepare("
            SELECT id, profile_image
            FROM users
            WHERE id = :id
              AND role = 'delivery'
            LIMIT 1
        ");

        $existingStmt->execute(['id' => $id]);

        $existingStaff = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingStaff) {
            staffDeleteImage($newImageFilename);

            staffSaveRedirect('Delivery staff account not found.');
        }

        $oldImageFilename = staffValidFilename(
            $existingStaff['profile_image'] ?? null
        );

        // Preserve the old image if no new image was uploaded.
        $profileImage = $newImageFilename ?? $oldImageFilename;

    } else {

        $profileImage = $newImageFilename;
    }

    // Check phone number uniqueness.
    if ($id) {

        $phoneStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE phone = :phone
              AND id != :excluded_id
            LIMIT 1
        ");

        $phoneStmt->execute([
            'phone' => $phone,
            'excluded_id' => $id
        ]);

    } else {

        $phoneStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE phone = :phone
            LIMIT 1
        ");

        $phoneStmt->execute(['phone' => $phone]);
    }

    if ($phoneStmt->fetchColumn()) {
        staffDeleteImage($newImageFilename);

        staffSaveRedirect(
            'This phone number is already registered.',
            $formLocation
        );
    }

    // Check email uniqueness.
    if ($email !== null) {

        if ($id) {

            $emailStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = :email
                  AND id != :excluded_id
                LIMIT 1
            ");

            $emailStmt->execute([
                'email' => $email,
                'excluded_id' => $id
            ]);

        } else {

            $emailStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $emailStmt->execute(['email' => $email]);
        }

        if ($emailStmt->fetchColumn()) {
            staffDeleteImage($newImageFilename);

            staffSaveRedirect(
                'This email address is already registered.',
                $formLocation
            );
        }
    }

    if ($id) {

        // UPDATE EXISTING STAFF.
        if ($password !== '') {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    full_name = :full_name,
                    phone = :phone,
                    email = :email,
                    password_hash = :password_hash,
                    profile_image = :profile_image,
                    account_status = :account_status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND role = 'delivery'
            ");

            $stmt->execute([
                'full_name' => $name,
                'phone' => $phone,
                'email' => $email,
                'password_hash' => $passwordHash,
                'profile_image' => $profileImage,
                'account_status' => $status,
                'id' => $id
            ]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    full_name = :full_name,
                    phone = :phone,
                    email = :email,
                    profile_image = :profile_image,
                    account_status = :account_status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND role = 'delivery'
            ");

            $stmt->execute([
                'full_name' => $name,
                'phone' => $phone,
                'email' => $email,
                'profile_image' => $profileImage,
                'account_status' => $status,
                'id' => $id
            ]);
        }

        // Remove old photograph only after successful database update.
        if (
            $newImageFilename !== null &&
            $oldImageFilename !== null &&
            $oldImageFilename !== $newImageFilename
        ) {
            staffDeleteImage($oldImageFilename);
        }

        $_SESSION['delivery_staff_success'] =
            'Delivery staff account updated successfully.';

    } else {

        // CREATE NEW DELIVERY STAFF.
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users (
                full_name,
                email,
                phone,
                password_hash,
                profile_image,
                role,
                account_status
            ) VALUES (
                :full_name,
                :email,
                :phone,
                :password_hash,
                :profile_image,
                'delivery',
                'active'
            )
        ");

        $stmt->execute([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'profile_image' => $profileImage
        ]);

        $_SESSION['delivery_staff_success'] =
            'Delivery staff account created successfully.';
    }

    header('Location: delivery_staff.php');
    exit;

} catch (Throwable $e) {

    // Remove a newly uploaded file if the database operation fails.
    staffDeleteImage($newImageFilename);

    error_log(
        'Food Mail delivery staff save error: ' . $e->getMessage()
    );

    if ($e instanceof PDOException && $e->getCode() === '23000') {
        staffSaveRedirect(
            'The phone number or email address is already registered.',
            $formLocation
        );
    }

    staffSaveRedirect(
        'Unable to save the delivery staff account. Please try again.',
        $formLocation
    );
}
