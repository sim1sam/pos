<?php
require_once '../config.php';
require_once '../includes/header.php';

// Search and pagination setup
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where = "WHERE name LIKE '%$safe_search%' OR email LIKE '%$safe_search%' OR mobile LIKE '%$safe_search%'";
}

$total_result = $conn->query("SELECT COUNT(*) AS total FROM customers $where");
$total_rows = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

$result = $conn->query("SELECT * FROM customers $where ORDER BY name ASC LIMIT $limit OFFSET $offset");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fb;
            font-family: 'Segoe UI', sans-serif;
        }
        h3 {
            font-weight: 600;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table th {
            white-space: nowrap;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #fdfdfd;
        }
        .input-group input[type="text"] {
            border-top-left-radius: 50px;
            border-bottom-left-radius: 50px;
        }
        .input-group .btn {
            border-top-right-radius: 50px;
            border-bottom-right-radius: 50px;
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.85rem;
        }
        .pagination .page-link {
            border-radius: 50px !important;
            margin: 0 3px;
        }
        @media (min-width: 992px) {
            .container .row.row-cols-1.row-cols-sm-2.row-cols-lg-3 {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Customer List</h3>
        <a href="customer_add.php" class="btn btn-primary btn-sm">+ Add Customer</a>
    </div>

    <form class="mb-3" method="get" action="">
        <div class="input-group shadow-sm">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name, email or mobile">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success text-center rounded-pill">Customer deleted successfully.</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm shadow-sm bg-white">
            <thead class="table-primary text-white text-center">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>Pin</th>
                    <th>State</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="align-middle">
                        <td class="text-center">#<?= htmlspecialchars($row['cus_id']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= nl2br(htmlspecialchars($row['address'])) ?></td>
                        <td><?= htmlspecialchars($row['city']) ?></td>
                        <td><?= htmlspecialchars($row['pin']) ?></td>
                        <td><?= htmlspecialchars($row['state']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['mobile']) ?></td>
                        <td class="text-center">
                            <a href="customer_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <a href="customer_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center text-muted">No customers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>
<?php require_once '../includes/footer.php'; ?>
