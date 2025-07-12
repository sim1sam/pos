<?php
require_once '../config.php';

// Check if ID is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$id = intval($_GET['id']);

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: users.php?deleted=1");
    exit;
} else {
    die("Error deleting user: " . $conn->error);
}
