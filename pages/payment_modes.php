<?php
require_once '../config.php';
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode_name = trim($_POST['mode_name']);
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    if ($mode_name !== '') {
        $stmt = $conn->prepare("SELECT id FROM payment_modes WHERE mode_name = ? AND id != ?");
        $stmt->bind_param("si", $mode_name, $edit_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            if ($edit_id > 0) {
                $stmt = $conn->prepare("UPDATE payment_modes SET mode_name = ? WHERE id = ?");
                $stmt->bind_param("si", $mode_name, $edit_id);
                $stmt->execute();
                header("Location: payment_modes.php?updated=1");
            } else {
                $stmt = $conn->prepare("INSERT INTO payment_modes (mode_name) VALUES (?)");
                $stmt->bind_param("s", $mode_name);
                $stmt->execute();
                header("Location: payment_modes.php?added=1");
            }
        } else {
            header("Location: payment_modes.php?exists=1");
        }
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM payment_modes WHERE id = $id");
    header("Location: payment_modes.php?deleted=1");
    exit;
}

$edit_mode = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM payment_modes WHERE id = $edit_id");
    $edit_mode = $res->fetch_assoc();
}

$modes = $conn->query("SELECT * FROM payment_modes ORDER BY created_at DESC");
?>

<div class="container py-4">
    <h3 class="mb-4">Payment Modes</h3>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Mode added successfully ✅
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Mode updated successfully ✏️
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Mode deleted successfully 🗑️
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['exists'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            This mode already exists ⚠️
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" class="row g-3 shadow-sm p-3 bg-white rounded mb-4">
        <div class="col-md-6">
            <input type="text" name="mode_name" class="form-control" placeholder="Enter payment mode (e.g., Cash, Bank)" value="<?= htmlspecialchars($edit_mode['mode_name'] ?? '') ?>" required>
            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_mode['id'] ?>">
            <?php endif; ?>
        </div>
        <div class="col-md-3 d-grid">
            <button type="submit" class="btn btn-<?= $edit_mode ? 'warning' : 'primary' ?>">
                <?= $edit_mode ? 'Update Mode' : 'Add Mode' ?>
            </button>
        </div>
        <?php if ($edit_mode): ?>
            <div class="col-md-3 d-grid">
                <a href="payment_modes.php" class="btn btn-secondary">Cancel</a>
            </div>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle shadow-sm bg-white">
            <thead class="table-primary text-white text-center">
                <tr>
                    <th>#</th>
                    <th>Mode Name</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($row = $modes->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center">#<?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['mode_name']) ?></td>
                        <td class="text-nowrap text-center"><?= date('d-M-Y H:i', strtotime($row['created_at'])) ?></td>
                        <td class="text-center text-nowrap">
                            <a href="payment_modes.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                            <a href="payment_modes.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this mode?')" class="btn btn-sm btn-outline-danger">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
