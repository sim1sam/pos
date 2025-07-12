<?php
require_once '../config.php';
require_once '../includes/header.php';

$success = $error = "";

function generateCustomerId($name, $conn) {
    $prefix = strtoupper(substr(trim($name), 0, 1));

    // Fix collation issue using CONVERT to match table collation
    $query = "SELECT COUNT(*) AS total FROM customers WHERE CONVERT(cus_id USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CONCAT(?, '%')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $count = $result['total'] ?? 0;
    return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {
        $header = fgetcsv($handle); // Skip the first line
        $inserted = 0;
        $failed = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            [$name, $address, $city, $pin, $state, $email, $mobile, $gstin, $prefix] = array_map('trim', array_pad($data, 9, ''));

            if ($name !== '') {
                $cus_id = generateCustomerId($name, $conn);

                $stmt = $conn->prepare("INSERT INTO customers (name, cus_id, address, city, pin, state, email, mobile, gstin, prefix, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssssssss", $name, $cus_id, $address, $city, $pin, $state, $email, $mobile, $gstin, $prefix);

                if ($stmt->execute()) {
                    $inserted++;
                } else {
                    $failed++;
                }
                $stmt->close();
            }
        }
        fclose($handle);

        $success = "$inserted customers imported successfully.";
        if ($failed > 0) {
            $error = "$failed rows failed to import.";
        }
    } else {
        $error = "Could not open the uploaded file.";
    }
}
?>

<div class="container py-4">
    <h3 class="mb-4">Bulk Upload Customers</h3>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="border p-4 rounded bg-light">
        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV File</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            <div class="form-text">CSV columns: Name, Address, City, Pin, State, Email, Mobile, GSTIN, Prefix</div>
        </div>
        <button type="submit" class="btn btn-primary">Upload & Import</button>
        <a href="customers.php" class="btn btn-secondary">Back to Customer List</a>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
