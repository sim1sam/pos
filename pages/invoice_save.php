<?php
require_once '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$customer_id = intval($_POST['customer_id'] ?? 0);
$invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
$due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
$gst_type = $_POST['gst_type'] ?? 'same';
$status = $_POST['action'] === 'draft' ? 'draft' : 'generated';

$item_desc = $_POST['item_desc'] ?? [];
$hsn_sac   = $_POST['hsn_sac'] ?? [];
$rate      = $_POST['rate'] ?? [];
$qty       = $_POST['qty'] ?? [];
$unit      = $_POST['unit'] ?? [];
$discount  = $_POST['discount'] ?? [];
$amount    = $_POST['amount'] ?? [];

// Fetch GST Rates
$gst_rates = [];
$res = $conn->query("SELECT hsn_sac, gst_rate FROM gst_config");
while ($row = $res->fetch_assoc()) {
    $gst_rates[$row['hsn_sac']] = $row['gst_rate'];
}

$subtotal = 0;
$total_tax = 0;
$items_data = [];

foreach ($item_desc as $i => $desc) {
    $rateVal = floatval($rate[$i] ?? 0);
    $qtyVal = intval($qty[$i] ?? 0);
    $discountVal = floatval($discount[$i] ?? 0);
    $amt = ($rateVal * $qtyVal) - $discountVal;
    $hsn = $hsn_sac[$i] ?? '';
    $gst_rate = floatval($gst_rates[$hsn] ?? 0);
    $tax = $amt * $gst_rate / 100;

    $sgst = $cgst = $igst = 0;
    if ($gst_type === 'same') {
        $sgst = $cgst = $tax / 2;
    } else {
        $igst = $tax;
    }

    $subtotal += $amt;
    $total_tax += $tax;

    $items_data[] = [
        'desc' => $desc,
        'hsn' => $hsn,
        'rate' => $rateVal,
        'qty' => $qtyVal,
        'unit' => $unit[$i] ?? '',
        'discount' => $discountVal,
        'amount' => $amt,
        'sgst' => $sgst,
        'cgst' => $cgst,
        'igst' => $igst,
        'total' => $amt + $sgst + $cgst + $igst
    ];
}

$total_amount = $subtotal + $total_tax;

// Generate Invoice Number (e.g. YYMMDD0001)
// Generate Invoice Number (YYMMDD0001), always two-digit month/day
// Use the submitted invoice_date (YYYY-MM-DD) instead of today
$invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
$date_prefix  = date('y', strtotime($invoice_date))  // two-digit year
               . date('m', strtotime($invoice_date))  // two-digit month
               . date('d', strtotime($invoice_date)); // two-digit day

$res = $conn->query("SELECT invoice_no FROM invoices WHERE invoice_no LIKE '$date_prefix%' ORDER BY invoice_no DESC LIMIT 1");
if ($row = $res->fetch_assoc()) {
    // grab just the 4-digit sequence suffix
    $last_no = intval(substr($row['invoice_no'], 6, 4));
    $next_no = str_pad($last_no + 1, 4, '0', STR_PAD_LEFT);
} else {
    $next_no = '0001';
}
$invoice_no = $date_prefix . $next_no;

// Insert invoice
$stmt = $conn->prepare("INSERT INTO invoices (customer_id, invoice_no, invoice_date, due_date, gst_type, status, subtotal, total_tax, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssdd", $customer_id, $invoice_no, $invoice_date, $due_date, $gst_type, $status, $subtotal, $total_tax, $total_amount);
$stmt->execute();
$invoice_id = $conn->insert_id;

// Insert item rows into invoice_details
foreach ($items_data as $item) {
    $stmt = $conn->prepare("INSERT INTO invoice_details (invoice_id, description, hsn_sac, rate, qty, unit, discount, amount, sgst_amount, cgst_amount, igst_amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdissddddd",
        $invoice_id,
        $item['desc'],
        $item['hsn'],
        $item['rate'],
        $item['qty'],
        $item['unit'],
        $item['discount'],
        $item['amount'],
        $item['sgst'],
        $item['cgst'],
        $item['igst'],
        $item['total']
    );
    $stmt->execute();
}

// Redirect to invoice list
header("Location: invoices.php?success=1");
exit;
