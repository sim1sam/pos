<?php
require_once '../config.php';
require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .card-box {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }
        @media (max-width: 576px) {
            h5.mb-1 {
                font-size: 1rem;
            }
            p.text-muted {
                font-size: 0.85rem;
            }
            .card-icon {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <h2 class="mb-4 text-center text-md-start">Customer Dashboard</h2>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">

        <!-- Add Customer -->
        <div class="col">
            <a href="<?= BASE_URL ?>/pages/customer_add.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-success">➕</div>
                    <h5 class="mb-1">Add Customer</h5>
                    <p class="text-muted mb-0">Register a new customer with contact & GST details</p>
                </div>
            </a>
        </div>

        <!-- All Customers -->
        <div class="col">
            <a href="<?= BASE_URL ?>/pages/customers.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-primary">👥</div>
                    <h5 class="mb-1">All Customers</h5>
                    <p class="text-muted mb-0">View, edit or filter the full customer list</p>
                </div>
            </a>
        </div>

        <!-- Customer Summary -->
        <div class="col">
            <a href="<?= BASE_URL ?>/pages/customers_summary.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-info">📊</div>
                    <h5 class="mb-1">Invoice & Payment Summary</h5>
                    <p class="text-muted mb-0">Check customer-wise billing and payment totals</p>
                </div>
            </a>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
