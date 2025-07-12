<?php
require_once '../config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid payment ID.");
}

$id = intval($_GET['id']);

// Get payment record
$result = $conn->query("SELECT * FROM payments WHERE id = $id");
$data = $result->fetch_assoc();

if (!$data) {
    die("Payment not found.");
}

// Fetch dropdown data
$customers = $conn->query("SELECT id, CONCAT(prefix, ' ', name) AS name FROM customers ORDER BY name ASC");
$payment_modes = $conn->query("SELECT id, mode_name FROM payment_modes ORDER BY mode_name ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Payment</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Edit Payment</h2>

    <form method="POST" action="payment_update.php">
        <input type="hidden" name="id" value="<?= $data['id'] ?>">

        <div class="mb-3">
            <label>Customer</label>
            <select name="customer_id" class="form-select" required>
                <option value="">-- Select Customer --</option>
                <?php while ($c = $customers->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($c['id'] == $data['customer_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Amount (₹)</label>
            <input type="number" name="amount" class="form-control" value="<?= $data['amount'] ?>" required>
        </div>

        <div class="mb-3">
            <label>Payment Mode</label>
            <select name="payment_mode_id" class="form-select" required>
                <option value="">-- Select Mode --</option>
                <?php while ($m = $payment_modes->fetch_assoc()): ?>
                    <option value="<?= $m['id'] ?>" <?= ($m['id'] == $data['payment_mode_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['mode_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= $data['payment_date'] ?>" required>
        </div>

        <div class="mb-3">
            <label>Note</label>
            <textarea name="note" class="form-control"><?= htmlspecialchars($data['note']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Update Payment</button>
    </form>
</div>
</body>
</html>
