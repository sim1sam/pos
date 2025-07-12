<?php
require_once '../config.php';
require_once '../includes/header.php';

// Default values
date_default_timezone_set('Asia/Dhaka');
$today = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+7 days'));
$selected_customer = $_GET['customer_id'] ?? '';

// Fetch customers
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");

// Fetch HSN codes
$hsn_data = [];
$hsn_codes = $conn->query("SELECT hsn_sac, gst_rate FROM gst_config ORDER BY hsn_sac ASC");
while ($row = $hsn_codes->fetch_assoc()) {
    $hsn_data[$row['hsn_sac']] = $row['gst_rate'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .add-customer-btn {
            background-color: #0d6efd;
            color: #fff;
            padding: 6px 14px;
            font-size: 14px;
            border-radius: 4px;
            border: none;
            transition: 0.3s ease;
        }
        .add-customer-btn:hover {
            background-color: #084298;
            text-decoration: none;
        }
        .form-control[readonly] {
            background-color: #e9ecef;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Create Invoice</h3>
        <a href="customer_add.php?return_to=invoice_create.php" class="add-customer-btn">+ Add Customer</a>
    </div>

    <form method="post" action="invoice_save.php">
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label">Prefix</label>
                <select name="prefix" class="form-select">
                    <option value="">(Blank)</option>
                    <option value="Mr.">Mr.</option>
                    <option value="Ms.">Ms.</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select" required>
                    <option value="">-- Select Customer --</option>
                    <?php $customers->data_seek(0);
                    while ($row = $customers->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= $row['id']==$selected_customer?'selected':'' ?>>
                            <?= htmlspecialchars($row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Invoice Date</label>
                <input type="date" name="invoice_date" class="form-control" value="<?= $today ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control" value="<?= $due_date ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">GST Type</label>
            <select name="gst_type" class="form-select">
                <option value="same">Same State</option>
                <option value="other">Other State</option>
            </select>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle text-center" id="items-table">
                <thead class="table-light">
                    <tr>
                        <th>SL#</th>
                        <th>Description</th>
                        <th>HSN/SAC</th>
                        <th>Rate</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Discount</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><input type="text" name="item_desc[]" class="form-control"></td>
                        <td>
                            <select name="hsn_sac[]" class="form-select">
                                <option value="">-- Select --</option>
                                <?php foreach($hsn_data as $code=>$rate): ?>
                                    <option value="<?= $code ?>"><?= htmlspecialchars($code) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="rate[]" class="form-control text-end"></td>
                        <td><input type="number" step="0.01" name="qty[]" class="form-control text-end"></td>
                        <td><input type="text" name="unit[]" class="form-control"></td>
                        <td><input type="number" step="0.01" name="discount[]" class="form-control text-end"></td>
                        <td><input type="number" step="1" name="amount[]" class="form-control text-end" readonly></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="add-item-btn" class="btn btn-outline-primary btn-sm">+ Add Item</button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4 offset-md-8">
                <div class="mb-2">
                    <label>Subtotal</label>
                    <input type="number" step="1" name="subtotal"      class="form-control text-end" readonly value="0">
                </div>
                <div class="mb-2">
                    <label>SGST</label>
                    <input type="number" step="1" name="sgst"          class="form-control text-end" readonly value="0">
                </div>
                <div class="mb-2">
                    <label>CGST</label>
                    <input name="cgst" step="1" name="total_amount"  class="form-control text-end" readonly value="0">
                </div>
                <div class="mb-2">
                    <label>IGST</label>
                    <input name="igst" step="1" name="total_amount"  class="form-control text-end" readonly value="0">
                </div>
                <div class="mb-2">
                    <label>Total Amount</label>
                    <input type="number" step="1" name="total_amount"  class="form-control text-end" readonly value="0">
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as Draft</button>
            <button type="submit" name="action" value="create" class="btn btn-success">Create Invoice</button>
        </div>
    </form>

<script>
const hsnRates = <?= json_encode($hsn_data) ?>;

function calculateAmounts() {
    let rows = document.querySelectorAll('#items-table tbody tr');
    let subtotal = 0;
    let totalTax = 0;
    const gstType = document.querySelector('[name="gst_type"]').value;

    rows.forEach(row => {
        const rate = parseFloat(row.querySelector('[name="rate[]"]').value) || 0;
        const qty = parseFloat(row.querySelector('[name="qty[]"]').value) || 0;
        const discount = parseFloat(row.querySelector('[name="discount[]"]').value) || 0;
        const hsnCode = row.querySelector('[name="hsn_sac[]"]').value;
        const gstRate = parseFloat(hsnRates[hsnCode]) || 0;
        const amountField = row.querySelector('[name="amount[]"]');

        const amount = (rate * qty) - discount;
        amountField.value = Math.round(amount);
        subtotal += amount;
        totalTax += amount * gstRate / 100;
    });

    document.querySelector('[name="subtotal"]').value = Math.round(subtotal);

    if (gstType === 'same') {
        document.querySelector('[name="sgst"]').value = Math.round(totalTax/2);
        document.querySelector('[name="cgst"]').value = Math.round(totalTax/2);
        document.querySelector('[name="igst"]').value = 0;
    } else {
        document.querySelector('[name="sgst"]').value = 0;
        document.querySelector('[name="cgst"]').value = 0;
        document.querySelector('[name="igst"]').value = Math.round(totalTax);
    }

    document.querySelector('[name="total_amount"]').value = Math.round(subtotal + totalTax);
}

function addRow() {
    const tbody = document.querySelector('#items-table tbody');
    const newRow = tbody.rows[0].cloneNode(true);
    const rowCount = tbody.rows.length + 1;
    newRow.querySelectorAll('input, select').forEach(f => f.value = '');
    newRow.querySelector('td').textContent = rowCount;
    tbody.appendChild(newRow);
}

document.addEventListener('DOMContentLoaded', () => {
    calculateAmounts();
    const table = document.getElementById('items-table');
    table.addEventListener('input', calculateAmounts);
    table.addEventListener('change', calculateAmounts);
    document.querySelector('[name="gst_type"]').addEventListener('change', calculateAmounts);
    document.getElementById('add-item-btn').addEventListener('click', () => {
        addRow();
        calculateAmounts();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>