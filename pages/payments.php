<?php
require_once '../config.php';
require_once '../includes/header.php';

$customers = $conn->query("SELECT id, CONCAT(COALESCE(prefix, ''), ' ', name) AS name FROM customers ORDER BY name ASC");
$payment_modes = $conn->query("SELECT id, mode_name FROM payment_modes ORDER BY mode_name ASC");

$where = [];
if (!empty($_GET['customer_id'])) {
    $where[] = "payments.customer_id = " . intval($_GET['customer_id']);
}
if (!empty($_GET['payment_mode_id'])) {
    $where[] = "payments.payment_mode_id = " . intval($_GET['payment_mode_id']);
}
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $from = $conn->real_escape_string($_GET['from_date']);
    $to = $conn->real_escape_string($_GET['to_date']);
    $where[] = "payments.payment_date BETWEEN '$from' AND '$to'";
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_query = "
SELECT SUM(payments.amount) AS total_amount
FROM payments
LEFT JOIN customers ON payments.customer_id = customers.id
LEFT JOIN payment_modes ON payments.payment_mode_id = payment_modes.id
$where_sql
";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_amount = number_format($total_row['total_amount'] ?? 0, 2);

$count_query = "SELECT COUNT(*) as total FROM payments LEFT JOIN customers ON payments.customer_id = customers.id LEFT JOIN payment_modes ON payments.payment_mode_id = payment_modes.id $where_sql";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$query = "
SELECT 
    payments.id,
    CONCAT(COALESCE(customers.prefix, ''), ' ', customers.name) AS customer_name,
    payments.amount,
    payments.payment_date,
    payment_modes.mode_name,
    payments.note
FROM payments
LEFT JOIN customers ON payments.customer_id = customers.id
LEFT JOIN payment_modes ON payments.payment_mode_id = payment_modes.id
$where_sql
ORDER BY payments.payment_date $order
LIMIT $limit OFFSET $offset
";
$results = $conn->query($query);

$summary_query = "
SELECT customers.id, CONCAT(COALESCE(customers.prefix, ''), ' ', customers.name) AS name, SUM(payments.amount) AS total_paid
FROM payments
LEFT JOIN customers ON payments.customer_id = customers.id
$where_sql
GROUP BY customers.id
ORDER BY name ASC
";
$summary_result = $conn->query($summary_query);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Payments</h3>
        <div>
            <a href="payment_add.php" class="btn btn-success btn-sm">+ Add Payment</a>
            <a href="export_payments_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-secondary btn-sm">Export to Excel</a>
        </div>
    </div>

    <form method="GET" class="row g-3 mb-4 shadow-sm p-3 bg-white rounded">
        <div class="col-md-3">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All</option>
                <?php mysqli_data_seek($customers, 0); while ($c = $customers->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($_GET['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Payment Mode</label>
            <select name="payment_mode_id" class="form-select">
                <option value="">All</option>
                <?php mysqli_data_seek($payment_modes, 0); while ($m = $payment_modes->fetch_assoc()): ?>
                    <option value="<?= $m['id'] ?>" <?= ($_GET['payment_mode_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['mode_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" class="form-control" value="<?= $_GET['from_date'] ?? '' ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" class="form-control" value="<?= $_GET['to_date'] ?? '' ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="alert alert-info rounded-pill text-center fw-semibold mb-4">
        Total Payment: ₹<?= $total_amount ?>
    </div>

    <div class="table-responsive mb-5">
        <table class="table table-bordered table-hover align-middle bg-white shadow-sm">
            <thead class="table-primary text-white text-center">
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Amount (₹)</th>
                    <th>Payment Mode</th>
                    <th>
                        <a href="?<?= http_build_query(array_merge($_GET, ['order' => $order === 'ASC' ? 'desc' : 'asc'])) ?>" class="text-white text-decoration-none">
                            Date <?= $order === 'ASC' ? '↑' : '↓' ?>
                        </a>
                    </th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $results->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center">#<?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td class="text-end">₹<?= number_format($row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($row['mode_name']) ?></td>
                        <td><?= date('d-M-Y', strtotime($row['payment_date'])) ?></td>
                        <td><?= htmlspecialchars($row['note']) ?></td>
                        <td class="text-center">
                            <a href="payment_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <a href="payment_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this payment?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <h5 class="text-muted">Customer-wise Totals</h5>
    <table class="table table-sm table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>Customer</th>
                <th>Total Paid (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $summary_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td class="text-end">₹<?= number_format($row['total_paid'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
