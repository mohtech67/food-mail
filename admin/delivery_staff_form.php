
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditing = $id !== null && $id !== false && $id > 0;

$staff = [
    'id' => '',
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'profile_image' => '',
    'account_status' => 'active'
];

if ($isEditing) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            profile_image,
            account_status
        FROM users
        WHERE id = :id
          AND role = 'delivery'
        LIMIT 1
    ");

    $stmt->execute(['id' => $id]);
    $existingStaff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingStaff) {
        $_SESSION['delivery_staff_error'] = 'Delivery staff member not found.';
        header('Location: delivery_staff.php');
        exit;
    }

    $staff = $existingStaff;
}

$pageTitle = ($isEditing ? 'Edit' : 'Add') . ' Delivery Staff | Food Mail Admin';

$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$error = $_SESSION['delivery_staff_error'] ?? '';
unset($_SESSION['delivery_staff_error']);

$imageFilename = $staff['profile_image'] ?? '';

$imagePath = __DIR__ . '/../uploads/delivery_staff/' . $imageFilename;

$hasImage = (
    $imageFilename !== '' &&
    basename($imageFilename) === $imageFilename &&
    is_file($imagePath)
);

$imageUrl = $hasImage
    ? '../uploads/delivery_staff/' . rawurlencode($imageFilename)
    : '';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.delivery-profile-preview {
    width: 145px;
    height: 145px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    overflow: hidden;
    border: 5px solid #e7f3eb;
    border-radius: 50%;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 55px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.delivery-profile-preview img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.delivery-upload-box {
    padding: 20px;
    background: #f8fbf9;
    border: 1px dashed #cbded0;
    border-radius: 13px;
}

.delivery-form-label {
    color: #263b2e;
    font-weight: 700;
}

.delivery-help-text {
    color: #718078;
    font-size: 12px;
}

.delivery-profile-info {
    padding: 22px;
    background: #f7faf8;
    border-radius: 13px;
}
</style>

<div class="container-fluid py-4">

    <div class="mb-4">

        <a href="delivery_staff.php" class="text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>
            Back to Delivery Staff
        </a>

        <h2 class="fw-bold mt-3 mb-1">
            <?= $isEditing ? 'Edit Delivery Staff' : 'Register Delivery Staff' ?>
        </h2>

        <p class="text-muted mb-0">
            <?= $isEditing
                ? 'Update the delivery personnel account and profile information.'
                : 'Create a new Food Mail delivery team member account.' ?>
        </p>

    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-8 col-xl-7">

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Staff Account Information</h5>
                </div>

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action="delivery_staff_save.php"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <input
                            type="hidden"
                            name="staff_id"
                            value="<?= (int)$staff['id'] ?>"
                        >

                        <!-- PROFILE PHOTOGRAPH -->

                        <div class="delivery-upload-box mb-4 text-center">

                            <div class="delivery-profile-preview" id="profilePreview">

                                <?php if ($hasImage): ?>

                                    <img
                                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Delivery staff profile photograph"
                                        id="profilePreviewImage"
                                    >

                                <?php else: ?>

                                    <i class="fas fa-user" id="profilePlaceholder"></i>

                                    <img
                                        src=""
                                        alt="Profile preview"
                                        id="profilePreviewImage"
                                        style="display:none;"
                                    >

                                <?php endif; ?>

                            </div>

                            <label class="form-label fw-bold d-block">
                                Profile Photograph
                            </label>

                            <p class="delivery-help-text mb-3">
                                Upload a clear photograph of the delivery staff member.
                            </p>

                            <input
                                type="file"
                                name="profile_image"
                                id="profileImageInput"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >

                            <small class="delivery-help-text d-block mt-2">
                                JPG, JPEG, PNG or WEBP. Maximum file size: 3 MB.
                            </small>

                            <small class="text-success d-block mt-2" id="imageStatus">
                                <?php if ($hasImage): ?>
                                    <i class="fas fa-check-circle me-1"></i>
                                    Existing profile photograph loaded.
                                <?php else: ?>
                                    <i class="fas fa-user-circle me-1"></i>
                                    No photograph uploaded. Default profile icon displayed.
                                <?php endif; ?>
                            </small>

                        </div>

                        <hr class="my-4">

                        <div class="mb-3">

                            <label class="form-label delivery-form-label">
                                Full Name <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                maxlength="150"
                                value="<?= htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Enter staff member's full name"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label delivery-form-label">
                                Phone Number <span class="text-danger">*</span>
                            </label>

                            <input
                                type="tel"
                                name="phone"
                                class="form-control"
                                maxlength="20"
                                value="<?= htmlspecialchars($staff['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="e.g. 0712345678"
                                required
                            >

                            <small class="delivery-help-text">
                                This number will be used for delivery staff login.
                            </small>

                        </div>

                        <div class="mb-3">

                            <label class="form-label delivery-form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="190"
                                value="<?= htmlspecialchars($staff['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="staff@example.com"
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label delivery-form-label">
                                <?= $isEditing ? 'New Password (optional)' : 'Login Password' ?>

                                <?php if (!$isEditing): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="12"
                                autocomplete="new-password"
                                <?= $isEditing ? '' : 'required' ?>
                                placeholder="<?= $isEditing
                                    ? 'Leave blank to keep the current password'
                                    : 'Enter at least 12 characters' ?>"
                            >

                            <small class="delivery-help-text">
                                Password must contain at least 12 characters.
                            </small>

                        </div>

                        <?php if ($isEditing): ?>

                            <div class="mb-4">

                                <label class="form-label delivery-form-label">
                                    Account Status
                                </label>

                                <select name="account_status" class="form-select">

                                    <option
                                        value="active"
                                        <?= $staff['account_status'] === 'active' ? 'selected' : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="suspended"
                                        <?= $staff['account_status'] === 'suspended' ? 'selected' : '' ?>
                                    >
                                        Suspended
                                    </option>

                                </select>

                            </div>

                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">

                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-save me-2"></i>
                                <?= $isEditing ? 'Save Changes' : 'Create Staff Account' ?>
                            </button>

                            <a href="delivery_staff.php" class="btn btn-outline-secondary px-4">
                                Cancel
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

        <div class="col-lg-4 col-xl-5">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <div class="delivery-profile-info">

                        <div class="text-success mb-3">
                            <i class="fas fa-truck fa-2x"></i>
                        </div>

                        <h5 class="fw-bold">Delivery Staff Profile</h5>

                        <p class="text-muted small">
                            Each delivery staff member has a personal profile
                            photograph and a secure login account.
                        </p>

                        <hr>

                        <div class="small">

                            <p>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Personal profile photograph
                            </p>

                            <p>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Secure login credentials
                            </p>

                            <p>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Access to assigned orders
                            </p>

                            <p>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Customer delivery information
                            </p>

                            <p class="mb-0">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Delivery progress updates
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const input = document.getElementById('profileImageInput');
    const previewImage = document.getElementById('profilePreviewImage');
    const placeholder = document.getElementById('profilePlaceholder');
    const status = document.getElementById('imageStatus');

    if (!input || !previewImage) {
        return;
    }

    let currentPreviewUrl = null;

    input.addEventListener('change', function () {

        const file = this.files && this.files[0];

        if (!file) {
            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG, JPEG, PNG and WEBP profile images are allowed.');
            this.value = '';
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            alert('The profile image must not exceed 3 MB.');
            this.value = '';
            return;
        }

        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
        }

        currentPreviewUrl = URL.createObjectURL(file);

        previewImage.src = currentPreviewUrl;
        previewImage.style.display = 'block';

        if (placeholder) {
            placeholder.style.display = 'none';
        }

        status.innerHTML =
            '<i class="fas fa-check-circle me-1"></i> New photograph selected.';
    });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
