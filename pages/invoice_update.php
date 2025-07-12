<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invoice_id'])) {
    die("Invalid request.");
}

$invoice_id = intval($_POST['invoice_id']);
$customer_id = intval($_POST['customer_id']);
$prefix = $_POST['prefix'] ?? '';
$invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
$due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
$gst_type = $_POST['gst_type'] ?? 'same';
$subtotal = round($_POST['subtotal'] ?? 0);
$sgst = round($_POST['sgst'] ?? 0);
$cgst = round($_POST['cgst'] ?? 0);
$igst = round($_POST['igst'] ?? 0);
$total_amount = round($_POST['total_amount'] ?? 0);
$action = $_POST['action'] ?? 'update';
$status = ($action === 'draft') ? 'Draft' : 'Due';

// Update invoice table
$stmt = $conn->prepare("UPDATE invoices SET prefix = ?, customer_id = ?, invoice_date = ?, due_date = ?, gst_type = ?, status = ?, subtotal = ?, total_discount = 0, total_tax = ?, total_amount = ? WHERE id = ?");
$total_tax = $sgst + $cgst + $igst;
$stmt->bind_param(
    "sissssiddi",
    $prefix,       // s: prefix
    $customer_id,  // i: customer_id
    $invoice_date, // s: invoice_date
    $due_date,     // s: due_date
    $gst_type,     // s: gst_type
    $status,       // s: status
    $subtotal,     // i: subtotal
    $total_tax,    // d: total_tax
    $total_amount, // d: total_amount
    $invoice_id    // i: id
);

$stmt->execute();
$stmt->close();

// Delete old items
$conn->query("DELETE FROM invoice_details WHERE invoice_id = $invoice_id");

// Insert new items
$item_desc = $_POST['item_desc'] ?? [];
$hsn_sac = $_POST['hsn_sac'] ?? [];
$rate = $_POST['rate'] ?? [];
$qty = $_POST['qty'] ?? [];
$unit = $_POST['unit'] ?? [];
$discount = $_POST['discount'] ?? [];
$amount = $_POST['amount'] ?? [];

for ($i = 0; $i < count($item_desc); $i++) {
    $desc = $conn->real_escape_string($item_desc[$i]);
    $hsn = $hsn_sac[$i];
    $r = floatval($rate[$i]);
    $q = floatval($qty[$i]);
    $u = $conn->real_escape_string($unit[$i]);
    $d = floatval($discount[$i]);
    $a = floatval($amount[$i]);

    // Calculate GST breakup
    $gst_rate = 0;
    $gst_query = $conn->query("SELECT gst_rate FROM gst_config WHERE hsn_sac = '$hsn' LIMIT 1");
    if ($gst_query && $gst_query->num_rows > 0) {
        $gst_rate = floatval($gst_query->fetch_assoc()['gst_rate']);
    }
    $tax_amount = ($a * $gst_rate) / 100;
    $sgst_amt = $cgst_amt = $igst_amt = 0;
    if ($gst_type === 'same') {
        $sgst_amt = $cgst_amt = round($tax_amount / 2, 2);
    } else {
        $igst_amt = round($tax_amount, 2);
    }

    $stmt = $conn->prepare("INSERT INTO invoice_details (invoice_id, description, hsn_sac, rate, qty, unit, discount, amount, sgst_amount, cgst_amount, igst_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $total = $a + $sgst_amt + $cgst_amt + $igst_amt;
    $stmt->bind_param(
    "issddsdddddd",
    $invoice_id,   // i: invoice_id
    $desc,         // s: description
    $hsn,          // s: hsn_sac
    $r,            // d: rate
    $q,            // d: qty
    $u,            // s: unit
    $d,            // d: discount
    $a,            // d: amount
    $sgst_amt,     // d: sgst_amount
    $cgst_amt,     // d: cgst_amount
    $igst_amt,     // d: igst_amount
    $total         // d: total
);
    $stmt->execute();
    $stmt->close();
}

header("Location: invoices.php?updated=1");
exit;
