<?php require_once '../config.php'; ?>
<?php require_once '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings</title>
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

<div class="container py-5">
    <h2 class="mb-4 text-center text-md-start">Settings</h2>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
        <div class="col">
            <a href="hsn_config.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-dark">🧾</div>
                    <h5 class="mb-1">GST Rates</h5>
                    <p class="text-muted mb-0">Manage HSN/SAC & tax rates</p>
                </div>
            </a>
        </div>

        <div class="col">
            <a href="payment_modes.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-dark">💳</div>
                    <h5 class="mb-1">Payment Modes</h5>
                    <p class="text-muted mb-0">Configure available payment types</p>
                </div>
            </a>
        </div>

        <div class="col">
            <a href="users.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-dark">👤</div>
                    <h5 class="mb-1">Users</h5>
                    <p class="text-muted mb-0">Manage system users and roles</p>
                </div>
            </a>
        </div>

        <div class="col">
            <a href="company_profile.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-dark">🏢</div>
                    <h5 class="mb-1">Company Profile</h5>
                    <p class="text-muted mb-0">Manage your company profile and info</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
