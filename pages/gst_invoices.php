<?php
require_once '../config.php';
require_once '../includes/header.php';

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");

// Get filters
$filter_customer = $_GET['customer_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

$conditions = ["is_gst_invoice = 1"];
if ($filter_customer !== '') {
    $conditions[] = "invoices.customer_id = " . intval($filter_customer);
}
if ($filter_status !== '') {
    $conditions[] = "invoices.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($from_date && $to_date) {
    $conditions[] = "invoice_date BETWEEN '" . $conn->real_escape_string($from_date) . "' AND '" . $conn->real_escape_string($to_date) . "'";
}

$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "SELECT invoices.*, customers.name AS customer_name
        FROM invoices
        JOIN customers ON invoices.customer_id = customers.id
        $where
        ORDER BY invoices.id DESC";
$result = $conn->query($sql);
?>

<div class="container py-4">
    <h3 class="mb-4">GST Invoices</h3>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success text-center">GST Invoice generated successfully.</div>
    <?php endif; ?>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All Customers</option>
                <?php $customers->data_seek(0); while ($cust = $customers->fetch_assoc()): ?>
                    <option value="<?= $cust['id'] ?>" <?= ($cust['id'] == $filter_customer ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cust['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="generated" <?= $filter_status === 'generated' ? 'selected' : '' ?>>Generated</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped text-center">
            <thead class="table-dark">
                <tr>
                    <th>SL#</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $i = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><a href="invoice_view.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['invoice_no']) ?></a></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= number_format($row['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($row['status'] === 'draft'): ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Generated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="invoice_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info">View</a>
                                <a href="invoice_create.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <a href="invoice_delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-outline-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-muted">No GST invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
