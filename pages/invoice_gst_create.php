<?php
require_once '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice ID.");
}

$original_id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manual_invoice_no = trim($_POST['manual_invoice_no']);

    if (empty($manual_invoice_no)) {
        die("Invoice number is required.");
    }

    // Optional: Check for duplicate invoice number
    $check = $conn->prepare("SELECT COUNT(*) as total FROM invoices WHERE invoice_no = ?");
    $check->bind_param("s", $manual_invoice_no);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    if ($result['total'] > 0) {
        die("Invoice number already exists.");
    }

    // Check if GST invoice already created
    $already_created = $conn->query("SELECT COUNT(*) AS total FROM invoices WHERE parent_invoice_id = $original_id AND is_gst_invoice = 1")->fetch_assoc();
    if ($already_created['total'] > 0) {
        die("GST invoice already generated for this invoice.");
    }

    // Fetch original invoice
    $invoice = $conn->query("SELECT * FROM invoices WHERE id = $original_id AND is_gst_invoice = 0")->fetch_assoc();
    if (!$invoice) {
        die("Original invoice not found or already a GST invoice.");
    }

    // Use manual input as invoice number
    $new_invoice_no = $manual_invoice_no;

    // Insert new GST invoice
    $stmt = $conn->prepare("INSERT INTO invoices (customer_id, invoice_no, invoice_date, due_date, gst_type, status, subtotal, total_discount, total_tax, total_amount, is_gst_invoice, parent_invoice_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    $stmt->bind_param("issssssdddi",
        $invoice['customer_id'],
        $new_invoice_no,
        $invoice['invoice_date'],
        $invoice['due_date'],
        $invoice['gst_type'],
        $invoice['status'],
        $invoice['subtotal'],
        $invoice['total_discount'],
        $invoice['total_tax'],
        $invoice['total_amount'],
        $original_id
    );
    $stmt->execute();
    $new_invoice_id = $conn->insert_id;

    // Clone invoice details
    $details = $conn->query("SELECT * FROM invoice_details WHERE invoice_id = $original_id");
    while ($item = $details->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO invoice_details (invoice_id, description, hsn_sac, rate, qty, unit, discount, amount, sgst_amount, cgst_amount, igst_amount, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdissddddd",
            $new_invoice_id,
            $item['description'],
            $item['hsn_sac'],
            $item['rate'],
            $item['qty'],
            $item['unit'],
            $item['discount'],
            $item['amount'],
            $item['sgst_amount'],
            $item['cgst_amount'],
            $item['igst_amount'],
            $item['total']
        );
        $stmt->execute();
    }

    // Redirect to invoice list
    header("Location: gst_invoices.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enter GST Invoice Number</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h3 class="mb-4">Generate GST Invoice for Invoice ID: <?= htmlspecialchars($original_id) ?></h3>
    <form method="POST">
        <div class="mb-3">
            <label for="manual_invoice_no" class="form-label">Enter GST Invoice Number:</label>
            <input type="text" class="form-control" name="manual_invoice_no" id="manual_invoice_no" required>
        </div>
        <button type="submit" class="btn btn-primary">Create GST Invoice</button>
    </form>
</body>
</html>