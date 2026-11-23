
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function customerEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function customerMoney($amount): string
{
    return 'KSh ' . number_format((float)$amount, 2);
}

$search = trim($_GET['search'] ?? '');

$ordersSummary = "
    SELECT
        customer_phone,
        MAX(customer_name) AS customer_name,
        COUNT(*) AS total_orders,
        SUM(total_amount) AS total_spent,
        MAX(created_at) AS last_order
    FROM orders
    GROUP BY customer_phone
";

$sql = "
    SELECT *
    FROM (
        SELECT
            u.id AS user_id,
            u.full_name,
            u.phone,
            u.email,
            u.account_status,
            COALESCE(o.total_orders, 0) AS total_orders,
            COALESCE(o.total_spent, 0) AS total_spent,
            o.last_order,
            'Registered' AS customer_type
        FROM users u
        LEFT JOIN ($ordersSummary) o
            ON o.customer_phone = u.phone
        WHERE u.role = 'customer'

        UNION ALL

        SELECT
            NULL AS user_id,
            o.customer_name AS full_name,
            o.customer_phone AS phone,
            NULL AS email,
            NULL AS account_status,
            o.total_orders,
            o.total_spent,
            o.last_order,
            'Guest' AS customer_type
        FROM ($ordersSummary) o
        LEFT JOIN users u
            ON u.phone = o.customer_phone
            AND u.role = 'customer'
        WHERE u.id IS NULL
    ) AS customers
    WHERE (
        :search = ''
        OR full_name LIKE :search_name
        OR phone LIKE :search_phone
        OR email LIKE :search_email
    )
    ORDER BY last_order DESC
    LIMIT 300
";

$stmt = $pdo->prepare($sql);
$searchTerm = '%' . $search . '%';

$stmt->execute([
    'search' => $search,
    'search_name' => $searchTerm,
    'search_phone' => $searchTerm,
    'search_email' => $searchTerm
]);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCustomers = count($customers);
$registeredCustomers = 0;
$guestCustomers = 0;
$totalCustomerOrders = 0;
$totalCustomerRevenue = 0;

foreach ($customers as $customer) {
    if ($customer['customer_type'] === 'Registered') {
        $registeredCustomers++;
    } else {
        $guestCustomers++;
    }

    $totalCustomerOrders += (int)$customer['total_orders'];
    $totalCustomerRevenue += (float)$customer['total_spent'];
}

$pageTitle = 'Customer Management';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-content">

    <div class="page-header">
        <div>
            <h1>Customer Management</h1>
            <p>View customers, contact details and purchasing activity.</p>
        </div>
    </div>

    <!-- CUSTOMER STATISTICS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-card-content">
                <p>Customers Listed</p>
                <h2><?= number_format($totalCustomers); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <p>Registered Customers</p>
                <h2><?= number_format($registeredCustomers); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <p>Guest Customers</p>
                <h2><?= number_format($guestCustomers); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <p>Orders from Listed Customers</p>
                <h2><?= number_format($totalCustomerOrders); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-content">
                <p>Order Revenue</p>
                <h2><?= customerMoney($totalCustomerRevenue); ?></h2>
            </div>
        </div>

    </div>

    <!-- SEARCH -->
    <div class="card">
        <div class="card-body">

            <form method="GET" action="customers.php" class="customer-search">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search by customer name, phone or email..."
                    value="<?= customerEscape($search); ?>"
                >

                <button type="submit" class="btn btn-primary">
                    Search
                </button>

                <?php if ($search !== ''): ?>
                    <a href="customers.php" class="btn btn-secondary">
                        Clear
                    </a>
                <?php endif; ?>

            </form>

            <p class="text-muted">
                Showing a maximum of 300 matching customer records.
            </p>

        </div>
    </div>

    <!-- CUSTOMER TABLE -->
    <div class="card">
        <div class="card-header">
            <h2>Customer Directory</h2>
        </div>

        <div class="card-body">

            <?php if (empty($customers)): ?>

                <div class="alert alert-info">
                    No customers found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($customers as $customer): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= customerEscape($customer['full_name']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= customerEscape($customer['phone']); ?>
                                </td>

                                <td>
                                    <?= customerEscape($customer['email'] ?: '—'); ?>
                                </td>

                                <td>
                                    <?php if ($customer['customer_type'] === 'Registered'): ?>
                                        <span class="status-badge status-confirmed">
                                            Registered
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            Guest
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= number_format((int)$customer['total_orders']); ?>
                                </td>

                                <td>
                                    <?= customerMoney($customer['total_spent']); ?>
                                </td>

                                <td>
                                    <?= customerEscape($customer['last_order'] ?: 'No orders'); ?>
                                </td>

                                <td>
                                    <a
                                        href="customer_details.php?phone=<?= urlencode($customer['phone']); ?>"
                                        class="btn btn-primary btn-sm"
                                    >
                                        View Details
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<style>
.customer-search {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}

.customer-search .form-control {
    flex: 1;
    min-width: 220px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}

.stat-card p {
    color: #64748b;
    margin-bottom: 8px;
}

.stat-card h2 {
    font-size: 22px;
    margin: 0;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    white-space: nowrap;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-confirmed {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
