<?php
require_once '../config.php';
require_once '../includes/header.php';

$customers = $conn->query("SELECT id, CONCAT(COALESCE(prefix, ''), ' ', name) AS name FROM customers ORDER BY id DESC");
$payment_modes = $conn->query("SELECT id, mode_name FROM payment_modes ORDER BY id DESC");
$today = date('Y-m-d');
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="card-title mb-4 text-center">Add New Payment</h4>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            ✅ Payment recorded successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="payment_save.php">
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select class="form-select" name="customer_id" required>
                                <option value="">-- Select Customer --</option>
                                <?php while ($row = $customers->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name'] ?? '') ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_mode_id" required>
                                <option value="">-- Select Mode --</option>
                                <?php while ($row = $payment_modes->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['mode_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" value="<?= $today ?>" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Note (optional)</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Save Payment</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="payments.php" class="text-decoration-none">← Back to Payments</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
