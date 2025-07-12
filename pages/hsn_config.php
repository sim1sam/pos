<?php
require_once '../config.php';
require_once '../includes/header.php';

$success = $error = "";

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM gst_config WHERE id = $id");
    header("Location: hsn_config.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hsn_sac = trim($_POST['hsn_sac'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $gst_rate = floatval($_POST['gst_rate'] ?? 0);
    $edit_id = intval($_POST['edit_id'] ?? 0);

    if ($hsn_sac && $gst_rate > 0) {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE gst_config SET hsn_sac = ?, description = ?, gst_rate = ? WHERE id = ?");
            $stmt->bind_param("ssdi", $hsn_sac, $description, $gst_rate, $edit_id);
            $success = "HSN/SAC code updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO gst_config (hsn_sac, description, gst_rate) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $hsn_sac, $description, $gst_rate);
            $success = "HSN/SAC code added successfully.";
        }

        if (!$stmt->execute()) {
            $error = "Failed to save HSN/SAC code.";
        }
    } else {
        $error = "HSN/SAC and valid GST rate are required.";
    }
}

$edit_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM gst_config WHERE id = $id LIMIT 1");
    $edit_data = $res->fetch_assoc();
}

$result = $conn->query("SELECT * FROM gst_config ORDER BY created_at DESC");
?>

<div class="container py-4">
    <h3 class="mb-4">HSN / SAC Code Configuration</h3>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="row g-3 shadow-sm p-3 bg-white rounded mb-4">
        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">

        <div class="col-md-3">
            <label class="form-label">HSN / SAC Code *</label>
            <input type="text" name="hsn_sac" class="form-control" required value="<?= htmlspecialchars($edit_data['hsn_sac'] ?? '') ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($edit_data['description'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">GST Rate (%) *</label>
            <input type="number" step="0.01" name="gst_rate" class="form-control" required value="<?= htmlspecialchars($edit_data['gst_rate'] ?? '') ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <?= $edit_data ? 'Update' : 'Add' ?>
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle shadow-sm bg-white">
            <thead class="table-primary text-white text-center">
                <tr>
                    <th>#</th>
                    <th>HSN/SAC</th>
                    <th>Description</th>
                    <th>GST Rate (%)</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): $i = 1; ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center">#<?= $i++ ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['hsn_sac']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end"><?= number_format($row['gst_rate'], 2) ?>%</td>
                            <td class="text-nowrap text-center"><?= date('d-M-Y', strtotime($row['created_at'])) ?></td>
                            <td class="text-center text-nowrap">
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>