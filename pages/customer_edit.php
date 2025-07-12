<?php
require_once '../config.php';
require_once '../includes/header.php';

// Get customer ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid customer ID.");
}

$id = intval($_GET['id']);
$success = $error = "";

// Fetch customer
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    die("Customer not found.");
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id      = intval($_POST["id"]);
    $name    = trim($_POST["name"]);
    $address = trim($_POST["address"]);
    $city    = trim($_POST["city"]);
    $pin     = trim($_POST["pin"]);
    $state   = trim($_POST["state"]);
    $email   = trim($_POST["email"]);
    $mobile  = trim($_POST["mobile"]);
    $gstin   = trim($_POST["gstin"]);

    $check = $conn->prepare("SELECT cus_id FROM customers WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $cus_data = $check->get_result()->fetch_assoc();
    $check->close();

    $cus_id = $cus_data['cus_id'];

    if (!$cus_id) {
        $prefix = strtoupper(substr($name, 0, 1));
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM customers WHERE CONVERT(cus_id USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONCAT(?, '%')");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = $result['total'] ?? 0;
        $cus_id = $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $update = $conn->prepare("UPDATE customers SET cus_id = ? WHERE id = ?");
        $update->bind_param("si", $cus_id, $id);
        $update->execute();
        $update->close();
    }

    $stmt = $conn->prepare("UPDATE customers SET name=?, address=?, city=?, pin=?, state=?, email=?, mobile=?, gstin=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $name, $address, $city, $pin, $state, $email, $mobile, $gstin, $id);

    if ($stmt->execute()) {
        $success = "Customer updated successfully.";
        $customer = ["name" => $name, "address" => $address, "city" => $city, "pin" => $pin, "state" => $state, "email" => $email, "mobile" => $mobile, "gstin" => $gstin];
    } else {
        $error = "Error updating customer.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h3 class="mb-4">Edit Customer</h3>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="col-md-6">
            <label class="form-label">Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($customer['name']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($customer['address']) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Pin Code</label>
            <input type="text" name="pin" class="form-control" value="<?= htmlspecialchars($customer['pin']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">State</label>
            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($customer['state']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email']) ?>" placeholder="">
        </div>
        <div class="col-md-6">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($customer['mobile']) ?>" placeholder="">
        </div>
        <div class="col-md-6">
            <label class="form-label">GSTIN</label>
            <input type="text" name="gstin" class="form-control" value="<?= htmlspecialchars($customer['gstin']) ?>" placeholder="">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="customers.php" class="btn btn-secondary">Back to List</a>
        </div>
    </form>
</div>
<?php require_once '../includes/footer.php'; ?>
