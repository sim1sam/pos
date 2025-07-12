<?php
require_once '../config.php';
require_once '../includes/header.php';

$name = $address = $city = $pin = $state = $email = $mobile = $gstin = "";
$success = $error = "";
$return_to = $_GET['return_to'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name    = trim($_POST["name"]);
    $address = trim($_POST["address"]);
    $city    = trim($_POST["city"]);
    $pin     = trim($_POST["pin"]);
    $state   = trim($_POST["state"]);
    $email   = trim($_POST["email"]);
    $mobile  = trim($_POST["mobile"]);
    $gstin   = trim($_POST["gstin"]);

    if ($name) {
        // ✅ Generate cus_id
        $prefix = strtoupper(substr($name, 0, 1));
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM customers WHERE CONVERT(cus_id USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONCAT(?, '%')");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = $result['total'] ?? 0;
        $cus_id = $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // ✅ Insert with cus_id
        $stmt = $conn->prepare("INSERT INTO customers (name, cus_id, address, city, pin, state, email, mobile, gstin, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssss", $name, $cus_id, $address, $city, $pin, $state, $email, $mobile, $gstin);

        if ($stmt->execute()) {
            $last_id = $conn->insert_id;
            if ($return_to) {
                header("Location: $return_to?customer_id=$last_id");
                exit;
            }
            $success = "Customer added successfully. ID: $cus_id";
            $name = $address = $city = $pin = $state = $email = $mobile = $gstin = "";
        } else {
            $error = "Error adding customer.";
        }
    } else {
        $error = "Name is required.";
    }
}
?>

<div class="container py-4">
    <h3 class="mb-4">Add New Customer</h3>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($name) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($address) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($city) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Pin Code</label>
            <input type="text" name="pin" class="form-control" value="<?= htmlspecialchars($pin) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">State</label>
            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($state) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($mobile) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">GSTIN</label>
            <input type="text" name="gstin" class="form-control" placeholder="Enter GSTIN (optional)" value="<?= htmlspecialchars($gstin) ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success">Save Customer</button>
            <a href="customers.php" class="btn btn-secondary">Back to List</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
