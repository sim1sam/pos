<?php
require_once '../config.php';
require_once '../includes/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid invoice ID.";
    exit;
}

$invoice_id = intval($_GET['id']);
$invoice = $conn->query("SELECT * FROM invoices WHERE id = $invoice_id")->fetch_assoc();
if (!$invoice) {
    echo "Invoice not found.";
    exit;
}

$customer = $conn->query("SELECT * FROM customers WHERE id = {$invoice['customer_id']}")->fetch_assoc();
$items_result = $conn->query("SELECT * FROM invoice_details WHERE invoice_id = $invoice_id");
$customers = $conn->query("SELECT id, name, prefix FROM customers ORDER BY name ASC");
$hsn_data = [];
$hsn_codes = $conn->query("SELECT * FROM gst_config ORDER BY hsn_sac ASC");
while ($row = $hsn_codes->fetch_assoc()) {
    $hsn_data[$row['hsn_sac']] = $row['gst_rate'];
}
function selected($a, $b) {
    return $a == $b ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        const hsnRates = <?= json_encode($hsn_data) ?>;

        function calculateAmounts() {
            let rows = document.querySelectorAll('#items-table tbody tr');
            let subtotal = 0;
            let totalTax = 0;
            const gstType = document.querySelector('[name="gst_type"]').value;

            rows.forEach((row, index) => {
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
                document.querySelector('[name="sgst"]').value = Math.round(totalTax / 2);
                document.querySelector('[name="cgst"]').value = Math.round(totalTax / 2);
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
            const rowCount = tbody.rows.length;
            const newRow = tbody.rows[0].cloneNode(true);
            newRow.querySelectorAll('input, select').forEach(field => field.value = '');
            newRow.querySelector('td').textContent = rowCount + 1;
            tbody.appendChild(newRow);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector('#items-table').addEventListener('input', calculateAmounts);
            document.querySelector('[name="gst_type"]').addEventListener('change', calculateAmounts);
            document.querySelector('#add-item-btn').addEventListener('click', addRow);
            calculateAmounts();
        });
    </script>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Edit Invoice - <?= htmlspecialchars($invoice['invoice_no']) ?></h3>
    </div>
    <form method="post" action="invoice_update.php">
      <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    
      <div class="row g-3 mb-3">
        <!-- Prefix -->
        <div class="col-md-2">
          <label class="form-label">Prefix</label>
          <select name="prefix" class="form-select">
            <option value=""       <?= selected('', $invoice['prefix']) ?>>(Blank)</option>
            <option value="Mr."   <?= selected('Mr.',  $invoice['prefix']) ?>>Mr.</option>
            <option value="Ms."   <?= selected('Ms.',  $invoice['prefix']) ?>>Ms.</option>
          </select>
        </div>
    
        <!-- Customer -->
        <div class="col-md-4">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-select" required>
            <option value="">-- Select --</option>
            <?php while($c = $customers->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>"
                <?= selected($c['id'], $invoice['customer_id']) ?>>
                <?= htmlspecialchars($c['prefix'] . ' ' . $c['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
    
        <!-- Dates -->
        <div class="col-md-3">
          <label class="form-label">Invoice Date</label>
          <input type="date" name="invoice_date" class="form-control"
                 value="<?= htmlspecialchars($invoice['invoice_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Due Date</label>
          <input type="date" name="due_date" class="form-control"
                 value="<?= htmlspecialchars($invoice['due_date']) ?>">
        </div>
      </div>
    
      <!-- GST Type -->
      <div class="mb-4">
        <label class="form-label">GST Type</label>
        <select name="gst_type" class="form-select">
          <option value="same"  <?= selected('same',  $invoice['gst_type']) ?>>Same State</option>
          <option value="other" <?= selected('other', $invoice['gst_type']) ?>>Other State</option>
        </select>
      </div>
    
      <!-- Items Table -->
      <div class="table-responsive">
        <table class="table table-bordered align-middle text-center" id="items-table">
          <thead class="table-light">
            <tr>
              <th>SL#</th><th>Description</th><th>HSN/SAC</th>
              <th>Rate</th><th>Qty.</th><th>Unit</th><th>Disc.</th><th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; while($item = $items_result->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><input type="text" name="item_desc[]" class="form-control"
                         value="<?= htmlspecialchars($item['description']) ?>"></td>
              <td>
                <select name="hsn_sac[]" class="form-select">
                  <option value="">--</option>
                  <?php foreach($hsn_data as $code => $rate): ?>
                    <option value="<?= $code ?>"
                      <?= selected($code, $item['hsn_sac']) ?>><?= $code ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" step="0.01" name="rate[]" class="form-control text-end"
                         value="<?= $item['rate'] ?>"></td>
              <td><input type="number" step="0.01" name="qty[]" class="form-control text-end"
                         value="<?= $item['qty'] ?>"></td>
              <td><input type="text" name="unit[]" class="form-control"
                         value="<?= htmlspecialchars($item['unit']) ?>"></td>
              <td><input type="number" step="0.01" name="discount[]" class="form-control text-end"
                         value="<?= $item['discount'] ?>"></td>
              <td><input type="number" step="1" name="amount[]" class="form-control text-end" readonly
                         value="<?= $item['amount'] ?>"></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <button type="button" id="add-item-btn" class="btn btn-outline-primary btn-sm">+ Add Item</button>
      </div>
    
      <!-- Totals -->
      <div class="row g-3">
        <div class="col-md-4 offset-md-8">
          <div class="mb-2">
            <label>Subtotal</label>
            <input type="number" name="subtotal" class="form-control text-end" readonly
                   value="<?= $invoice['subtotal'] ?>">
          </div>
          <div class="mb-2">
            <label>SGST</label>
            <input type="number" name="sgst" class="form-control text-end" readonly
                   value="<?= $gst_type==='same'?round($invoice['total_tax']/2,2):0 ?>">
          </div>
          <div class="mb-2">
            <label>CGST</label>
            <input type="number" name="cgst" class="form-control text-end" readonly
                   value="<?= $gst_type==='same'?round($invoice['total_tax']/2,2):0 ?>">
          </div>
          <div class="mb-2">
            <label>IGST</label>
            <input type="number" name="igst" class="form-control text-end" readonly
                   value="<?= $gst_type!=='same'?round($invoice['total_tax'],2):0 ?>">
          </div>
          <div class="mb-2">
            <label>Total Amount</label>
            <input type="number" name="total_amount" class="form-control text-end" readonly
                   value="<?= $invoice['total_amount'] ?>">
          </div>
        </div>
      </div>
    
      <!-- Actions -->
      <div class="text-end mt-4">
        <button type="submit" name="action" value="draft"  class="btn btn-secondary">Save as Draft</button>
        <button type="submit" name="action" value="update" class="btn btn-success">Update Invoice</button>
      </div>
    </form>

</div>
<script>
const hsnRates = <?= json_encode($hsn_data) ?>;

function calculateAmounts() { /* …same code as create… */ }
function addRow()          { /* …same code as create… */ }

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