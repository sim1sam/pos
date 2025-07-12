<?php
require_once '../config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid customer ID.");
}

$customer_id = intval($_GET['id']);

// Fetch customer info
$customer = $conn->query("SELECT CONCAT_WS(' ', prefix, name) AS name, email, mobile FROM customers WHERE id = $customer_id")->fetch_assoc();
if (!$customer) {
    die("Customer not found.");
}

// Fetch totals
$total_invoice = $conn->query("SELECT SUM(total_amount) as total FROM invoices WHERE customer_id = $customer_id")->fetch_assoc()['total'] ?? 0;
$total_payment = $conn->query("SELECT SUM(amount) as total FROM payments WHERE customer_id = $customer_id")->fetch_assoc()['total'] ?? 0;
$due_amount = $total_invoice - $total_payment;

// Fetch detailed data
$invoices = $conn->query("SELECT invoice_no, invoice_date, total_amount, status FROM invoices WHERE customer_id = $customer_id ORDER BY invoice_date DESC");
$payments = $conn->query("SELECT amount, payment_date, note FROM payments WHERE customer_id = $customer_id ORDER BY payment_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fb;
            font-family: 'Segoe UI', sans-serif;
        }
        .summary-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.05);
            padding: 20px;
        }
        .section-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
            padding: 20px;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #f1f3f5;
        }
        .badge-status {
            text-transform: capitalize;
        }
        @media print {
            .btn-print, .btn-back {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= htmlspecialchars($customer['name']) ?> <small class="text-muted" style="font-size: 18px;">Details</small></h2>
        <div>
            <a href="customers_summary.php" class="btn btn-secondary btn-back">← Back</a>
            <button class="btn btn-outline-primary btn-print" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>

    <div class="summary-card mb-4">
        <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?> &nbsp;&nbsp; <strong>Mobile:</strong> <?= htmlspecialchars($customer['mobile']) ?></p>
        <div class="row mt-3">
            <div class="col-md-4">
                <h6>Total Invoice</h6>
                <p class="text-primary fw-semibold">₹<?= number_format($total_invoice, 2) ?></p>
            </div>
            <div class="col-md-4">
                <h6>Total Payment</h6>
                <p class="text-success fw-semibold">₹<?= number_format($total_payment, 2) ?></p>
            </div>
            <div class="col-md-4">
                <h6>Due Amount</h6>
                <p class="text-danger fw-semibold">₹<?= number_format($due_amount, 2) ?></p>
            </div>
        </div>
    </div>

    <div class="section-card">
        <h5>Invoices</h5>
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($inv = $invoices->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
                        <td><?= date('d-M-Y', strtotime($inv['invoice_date'])) ?></td>
                        <td><span class="badge bg-info text-dark badge-status"><?= htmlspecialchars($inv['status']) ?></span></td>
                        <td><?= number_format($inv['total_amount'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="section-card">
        <h5>Payments</h5>
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount (₹)</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($pay = $payments->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($pay['payment_date'])) ?></td>
                        <td><?= number_format($pay['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($pay['note']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
