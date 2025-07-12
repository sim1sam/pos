<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/pos'); // your subfolder
}

require_once BASE_PATH . '/config.php';

// Fetch logo from company_profile
$logo_path = BASE_URL . "/assets/images/logo.png"; // fallback

$result = $conn->query("SELECT logo FROM company_profile LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    if (!empty($row['logo'])) {
        $logo_candidate = '/uploads/' . ltrim($row['logo'], '/');
        if (file_exists(BASE_PATH . $logo_candidate)) {
            $logo_path = BASE_URL . $logo_candidate;
        }
    }
}

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Inline style for sticky layout & mobile menu -->
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                position: fixed;
                top: 56px; /* just after the sticky header */
                right: 0;
                width: 100%;
                height: calc(100% - 56px);
                background-color: #fff;
                padding: 2rem;
                transition: transform 0.3s ease-in-out;
                transform: translateX(100%);
                z-index: 1050;
            }

            .navbar-collapse.show {
                transform: translateX(0);
            }

            .navbar-toggler:focus {
                box-shadow: none;
            }

            .navbar-nav .nav-link {
                font-size: 1.2rem;
                margin-bottom: 1rem;
                border-bottom: 1px solid #ddd;
                padding-bottom: 0.5rem;
            }

            .navbar-nav .nav-item:last-child .nav-link {
                border-bottom: none;
            }
        }

        @media (min-width: 992px) {
            .nav-link.active-desktop {
                font-weight: bold;
                color: #0d6efd !important;
                border-bottom: 2px solid #0d6efd;
            }
        }

        .navbar-brand img {
            height: 36px;
            object-fit: contain;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggler = document.querySelector('.navbar-toggler');
            const navbar = document.querySelector('#mainNavbar');

            toggler.addEventListener('click', function () {
                navbar.classList.toggle('show');
            });
        });
    </script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>/pages/dashboard.php">
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Company Logo" style="height:36px;">
        </a>
        <button class="navbar-toggler" type="button" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $current_page === 'dashboard.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/dashboard.php">🏠 Home</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_page === 'invoice_create.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/invoice_create.php">🧾 POS</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_page === 'invoice_all.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/invoice_all.php">📑 Invoices</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_page === 'customer_dashboard.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/customer_dashboard.php">👥 Customers</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_page === 'payment_add.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/payment_add.php">➕ Add Payment</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_page === 'settings.php' ? 'active-desktop' : '' ?>" href="<?= BASE_URL ?>/pages/settings.php">⚙️ Settings</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php">🚪 Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Page content starts -->
<main class="container my-4">
