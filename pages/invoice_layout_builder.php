<?php
require_once '../config.php';
require_once '../includes/header.php';

// Fetch database data
$company = $conn->query("SELECT * FROM company_profile LIMIT 1")->fetch_assoc();
$invoices = $conn->query("SELECT * FROM invoices ORDER BY id DESC LIMIT 5");
$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC LIMIT 5");
$items = $conn->query("SELECT * FROM invoice_details ORDER BY id DESC LIMIT 5");
$payments = $conn->query("SELECT * FROM payments ORDER BY id DESC LIMIT 5");
$payment_modes = $conn->query("SELECT * FROM payment_modes ORDER BY id DESC LIMIT 5");
$gst_configs = $conn->query("SELECT * FROM gst_config ORDER BY id DESC LIMIT 5");
$users = $conn->query("SELECT * FROM users ORDER BY id DESC LIMIT 5");
?>

<style>
body {
    font-family: "Trebuchet MS", sans-serif;
}
.section {
    border: 2px dashed #ccc;
    padding: 15px;
    margin-bottom: 10px;
    background-color: #f9f9f9;
    cursor: move;
}
.section h1, .section h2, .section h3 {
    margin-bottom: 10px;
}
#layout-container {
    min-height: 29.7cm;
    width: 21cm;
    border: 2px solid #eee;
    padding: 20px;
    background: #fff;
    overflow: auto;
}
.placeholder {
    background-color: #dff0d8;
    border: 2px dashed #3c763d;
    min-height: 40px;
    margin-bottom: 10px;
}
</style>

<div class="container mt-4">
    <h2>🧩 Invoice Layout Builder</h2>
    <p class="text-muted">Drag and drop the sections below to build your invoice layout. All fonts are set to <strong>Trebuchet MS</strong>.</p>

    <div class="row">
        <div class="col-md-4">
            <h4>Available Sections</h4>
            <div id="components">
                <div class="section" data-id="company_info">
                    <h2>Company Info</h2>
                    <p><strong><?= $company['name'] ?? '' ?></strong><br>
                    <?= $company['address'] ?? '' ?><br>
                    GSTIN: <?= $company['gstin'] ?? '' ?></p>
                </div>

                <div class="section" data-id="invoice_header">
                    <h2>Invoice Header</h2>
                    <?php if ($invoices->num_rows): ?>
                        <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <p>#<?= $inv['invoice_no'] ?> | <?= $inv['invoice_date'] ?></p>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <div class="section" data-id="items_table">
                    <h2>Items Table</h2>
                    <ul>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <li><?= $item['description'] ?? 'Item' ?> — ₹<?= $item['amount'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="customer_info">
                    <h2>Customer Info</h2>
                    <ul>
                        <?php while ($cust = $customers->fetch_assoc()): ?>
                            <li><?= $cust['prefix'] . ' ' . $cust['name'] ?> — <?= $cust['gstin'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="gst_config">
                    <h2>HSN / GST Config</h2>
                    <ul>
                        <?php while ($gst = $gst_configs->fetch_assoc()): ?>
                            <li><?= $gst['hsn_sac'] ?> — <?= $gst['gst_rate'] ?>%</li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="payments">
                    <h2>Payments</h2>
                    <ul>
                        <?php while ($pay = $payments->fetch_assoc()): ?>
                            <li>₹<?= $pay['amount'] ?> on <?= $pay['payment_date'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="payment_modes">
                    <h2>Payment Modes</h2>
                    <ul>
                        <?php while ($mode = $payment_modes->fetch_assoc()): ?>
                            <li><?= $mode['mode_name'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="users">
                    <h2>Users</h2>
                    <ul>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <li><?= $user['username'] ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="section" data-id="gst_summary"><h2>GST Summary (H2)</h2></div>
                <div class="section" data-id="bank_details"><h2>Bank Details (H2)</h2></div>
                <div class="section" data-id="qr_code"><h2>QR Code (H2)</h2></div>
                <div class="section" data-id="declaration"><h2>Declaration (H2)</h2></div>
                <div class="section" data-id="footer"><h2>Footer (H2)</h2></div>
            </div>
        </div>

        <div class="col-md-8">
            <h4>Layout Canvas (A4 size)</h4>
            <form method="POST" action="save_invoice_layout.php">
                <div id="layout-container" class="mb-3"></div>
                <input type="hidden" name="layout_order" id="layout_order">
                <button type="submit" class="btn btn-success">💾 Save Layout</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
const components = document.getElementById('components');
const layout = document.getElementById('layout-container');

new Sortable(components, {
    group: {
        name: 'shared',
        pull: 'clone',
        put: false
    },
    sort: false,
    animation: 150
});

new Sortable(layout, {
    group: {
        name: 'shared',
        pull: true,
        put: true
    },
    animation: 150,
    onSort: function () {
        const order = [];
        layout.querySelectorAll('.section').forEach(el => {
            order.push(el.dataset.id);
        });
        document.getElementById('layout_order').value = JSON.stringify(order);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
