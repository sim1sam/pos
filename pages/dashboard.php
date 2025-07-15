<?php
require_once '../config.php';
require_once '../includes/header.php';

// Fetch totals
$invoice_q = $conn->query("SELECT SUM(total_amount) as total FROM invoices");
$total_invoice = $invoice_q->fetch_assoc()['total'] ?? 0;

$payment_q = $conn->query("SELECT SUM(amount) as total FROM payments");
$total_payment = $payment_q->fetch_assoc()['total'] ?? 0;

$due_amount = $total_invoice - $total_payment;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fb; font-family: 'Segoe UI', sans-serif; }
    .card-box {
      border: none;
      border-radius: 12px;
      transition: all 0.3s ease;
      background-color: #fff;
      box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    }
    .card-box:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }
    .card-icon {
      font-size: 30px;
      margin-bottom: 10px;
    }
    h2 { font-weight: 600; }
  </style>
</head>
<body>
<div class="container py-5">
  <div class="row g-4">
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/invoice_create.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">🧾</div>
          <h5>POS</h5>
          <p class="text-muted mb-0">Create a new invoice</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/invoice_all.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">📁</div>
          <h5>Invoices</h5>
          <p class="text-muted mb-0">View all invoices</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/customer_dashboard.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">👥</div>
          <h5>Customers</h5>
          <p class="text-muted mb-0">Manage all customers</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/customer_ledger.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">📒</div>
          <h5>Customer Ledger</h5>
          <p class="text-muted mb-0">Track customer transactions</p>
        </div>
      </a>
    </div>

    <div class="col-md-3">
      <div class="card card-box p-4 h-100">
        <div class="card-icon text-primary">💰</div>
        <h5>Total Invoice Amount</h5>
        <p class="text-muted">₹<?= number_format($total_invoice, 2) ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-box p-4 h-100">
        <div class="card-icon text-success">💵</div>
        <h5>Total Payment Received</h5>
        <p class="text-muted">₹<?= number_format($total_payment, 2) ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-box p-4 h-100">
        <div class="card-icon text-danger">🧮</div>
        <h5>Total Due Amount</h5>
        <p class="text-muted">₹<?= number_format($due_amount, 2) ?></p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-box p-4 h-100">
        <div class="card-icon text-info">📊</div>
        <h5>GST Report</h5>
        <p class="text-muted"><a href="<?= BASE_URL ?>/pages/gst_report.php" class="stretched-link text-decoration-none text-muted">View GST report</a></p>
      </div>
    </div>

    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/payment_add.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">➕</div>
          <h5>Add Payment</h5>
          <p class="text-muted mb-0">Record a new customer payment</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/payments.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">💳</div>
          <h5>Payments</h5>
          <p class="text-muted mb-0">View all payments</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/pages/settings.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">⚙️</div>
          <h5>Settings</h5>
          <p class="text-muted mb-0">Configure company settings</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?= BASE_URL ?>/logout.php" class="text-decoration-none text-dark">
        <div class="card card-box p-4 h-100">
          <div class="card-icon">🚪</div>
          <h5>Logout</h5>
          <p class="text-muted mb-0">Sign out from this session</p>
        </div>
      </a>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
