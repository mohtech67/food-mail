
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Delivery Staff | Food Mail Admin';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        id,
        full_name,
        email,
        phone,
        profile_image,
        account_status,
        created_at
    FROM users
    WHERE role = 'delivery'
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            full_name LIKE :search_name
            OR phone LIKE :search_phone
            OR email LIKE :search_email
        )
    ";

    $term = '%' . $search . '%';

    $params['search_name'] = $term;
    $params['search_phone'] = $term;
    $params['search_email'] = $term;
}

if (in_array($status, ['active', 'suspended'], true)) {
    $sql .= " AND account_status = :account_status";
    $params['account_status'] = $status;
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(account_status = 'active'), 0) AS active_count,
        COALESCE(SUM(account_status = 'suspended'), 0) AS suspended_count
    FROM users
    WHERE role = 'delivery'
");

$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalStaff = (int)($counts['total'] ?? 0);
$activeStaff = (int)($counts['active_count'] ?? 0);
$suspendedStaff = (int)($counts['suspended_count'] ?? 0);

$success = $_SESSION['delivery_staff_success'] ?? '';
$error = $_SESSION['delivery_staff_error'] ?? '';

unset(
    $_SESSION['delivery_staff_success'],
    $_SESSION['delivery_staff_error']
);

$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.delivery-staff-avatar {
    width: 54px;
    height: 54px;
    min-width: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    border: 2px solid #dceee2;
    background: #eaf7ef;
    color: #087a3e;
    font-size: 21px;
}

.delivery-staff-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.delivery-staff-name {
    color: #14251c;
    font-weight: 800;
}

.delivery-staff-table th {
    white-space: nowrap;
    font-size: 12px;
    text-transform: uppercase;
    color: #718078;
}

.delivery-staff-table td {
    vertical-align: middle;
}

.delivery-staff-actions {
    display: flex;
    justify-content: flex-end;
    gap: 7px;
}
</style>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h2 class="fw-bold mb-1">Delivery Staff</h2>
            <p class="text-muted mb-0">
                Manage Food Mail delivery personnel and their accounts.
            </p>
        </div>

        <a href="delivery_staff_form.php" class="btn btn-success">
            <i class="fas fa-user-plus me-2"></i>
            Add Delivery Staff
        </a>

    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Delivery Staff</div>
                    <div class="fs-2 fw-bold"><?= $totalStaff ?></div>
                    <i class="fas fa-users text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Active Accounts</div>
                    <div class="fs-2 fw-bold text-success"><?= $activeStaff ?></div>
                    <i class="fas fa-user-check text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Suspended Accounts</div>
                    <div class="fs-2 fw-bold text-danger"><?= $suspendedStaff ?></div>
                    <i class="fas fa-user-lock text-danger"></i>
                </div>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Delivery Personnel Directory</h5>
        </div>

        <div class="card-body">

            <form method="GET" class="row g-2 mb-4">

                <div class="col-md-6">
                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search by name, phone or email..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All account statuses</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                            Active
                        </option>
                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>
                            Suspended
                        </option>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-fill">
                        <i class="fas fa-search me-1"></i>
                        Search
                    </button>

                    <a href="delivery_staff.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-hover delivery-staff-table align-middle">

                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Account Status</th>
                            <th>Registered</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$staffMembers): ?>

                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                <p class="mb-2 fw-bold">No delivery staff found</p>
                                <span class="text-muted small">
                                    Add delivery personnel to get started.
                                </span>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($staffMembers as $staff): ?>

                            <?php
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
                            ?>

                            <tr>

                                <td>
                                    <div class="d-flex align-items-center gap-3">

                                        <div class="delivery-staff-avatar">

                                            <?php if ($hasImage): ?>

                                                <img
                                                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="<?= htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                >

                                            <?php else: ?>

                                                <i class="fas fa-user"></i>

                                            <?php endif; ?>

                                        </div>

                                        <div>
                                            <div class="delivery-staff-name">
                                                <?= htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>

                                            <small class="text-muted">
                                                Staff ID: <?= (int)$staff['id'] ?>
                                            </small>
                                        </div>

                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars($staff['phone'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($staff['email'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?php if ($staff['account_status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date('d M Y', strtotime($staff['created_at'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="delivery-staff-actions">

                                        <a
                                            href="delivery_staff_form.php?id=<?= (int)$staff['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit staff"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form
                                            method="POST"
                                            action="delivery_staff_toggle.php"
                                            onsubmit="return confirm('Are you sure you want to change this account status?');"
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

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $staff['account_status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $staff['account_status'] === 'active' ? 'Suspend account' : 'Activate account' ?>"
                                            >
                                                <i class="fas <?= $staff['account_status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
