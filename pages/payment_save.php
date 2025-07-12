<?php
require_once '../config.php';

$customer_id = intval($_POST['customer_id']);
$amount = floatval($_POST['amount']);
$payment_mode_id = intval($_POST['payment_mode_id']);
$payment_date = $_POST['payment_date'];
$note = trim($_POST['note'] ?? '');

if ($customer_id && $amount > 0 && $payment_mode_id && $payment_date) {
    $stmt = $conn->prepare("INSERT INTO payments (customer_id, amount, payment_mode_id, payment_date, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idiss", $customer_id, $amount, $payment_mode_id, $payment_date, $note);
    
    if ($stmt->execute()) {
        header("Location: payment_add.php?success=1");
        exit;
    } else {
        die("Error saving payment: " . $stmt->error);
    }
} else {
    die("All required fields must be filled.");
}
