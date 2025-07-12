<?php
require_once '../config.php';

$id = intval($_POST['id']);
$customer_id = intval($_POST['customer_id']);
$amount = floatval($_POST['amount']);
$payment_mode_id = intval($_POST['payment_mode_id']);
$payment_date = $_POST['payment_date'];
$note = trim($_POST['note'] ?? '');

if ($id && $customer_id && $amount > 0 && $payment_mode_id && $payment_date) {
    $stmt = $conn->prepare("UPDATE payments SET customer_id = ?, amount = ?, payment_mode_id = ?, payment_date = ?, note = ? WHERE id = ?");
    $stmt->bind_param("idissi", $customer_id, $amount, $payment_mode_id, $payment_date, $note, $id);

    if ($stmt->execute()) {
        header("Location: payments.php?updated=1");
        exit;
    } else {
        die("Error updating: " . $stmt->error);
    }
} else {
    die("Required fields missing.");
}
