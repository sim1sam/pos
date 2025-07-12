<?php
require_once '../config.php';

$success = $error = "";

// Check for user ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}
$id = intval($_GET['id']);

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = $_POST['password'];

    if ($username) {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $update->bind_param("ssi", $username, $hashed, $id);
        } else {
            $update = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $update->bind_param("si", $username, $id);
        }

        if ($update->execute()) {
            $success = "✅ User updated successfully.";
            // Reload fresh data
            $user['username'] = $username;
        } else {
            $error = "❌ Error updating user.";
        }
    } else {
        $error = "Username is required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="text-center mb-4">Edit User</h4>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep unchanged)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="users.php">← Back to Users</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
