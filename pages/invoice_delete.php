<?php
require_once '../config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice ID.");
}

$invoice_id = intval($_GET['id']);

// Delete invoice details first
$conn->query("DELETE FROM invoice_details WHERE invoice_id = $invoice_id");

// Delete invoice
$conn->query("DELETE FROM invoices WHERE id = $invoice_id");

// Redirect
header("Location: invoices.php?deleted=1");
exit;
