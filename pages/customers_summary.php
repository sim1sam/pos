<?php
require_once '../config.php'; ?>
<?php require_once '../includes/header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Summary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fb; font-family: 'Segoe UI', sans-serif; }
        .table-card { background: #fff; border-radius: 12px; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05); overflow: hidden; }
        h2 { font-weight: 600; }
        @media print { .btn-print, .btn-back, .search-box { display: none !important; } }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customer Summary</h2>
        <div>
            <a href="customer_dashboard.php" class="btn btn-secondary btn-back">← Back</a>
            <button class="btn btn-outline-primary btn-print" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>

    <div class="mb-3 search-box">
        <input type="text" id="searchInput" class="form-control" placeholder="Search customer name...">
    </div>

    <div class="table-card p-4" id="summaryData">
        <!-- Dynamic content loads here -->
    </div>
</div>

<script>
function loadCustomerSummary(page = 1) {
    const keyword = document.getElementById('searchInput').value;
    const xhr = new XMLHttpRequest();
    xhr.open("GET", "ajax/customers_summary_data.php?page=" + page + "&search=" + encodeURIComponent(keyword), true);
    xhr.onload = function () {
        document.getElementById('summaryData').innerHTML = this.responseText;
    };
    xhr.send();
}

// Load initial data
loadCustomerSummary();

// Bind search input
document.getElementById('searchInput').addEventListener('input', function () {
    loadCustomerSummary(1);
});

// Handle pagination links
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('page-link')) {
        e.preventDefault();
        const page = e.target.getAttribute('data-page');
        if (page) loadCustomerSummary(page);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
