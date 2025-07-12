<?php
require_once '../../config.php';

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// Count total matching
$count_sql = "
    SELECT COUNT(*) AS total FROM customers 
    WHERE CONCAT_WS(' ', prefix, name) LIKE '%$search%'
";
$total = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Fetch data
$query = "
    SELECT 
        c.id,
        CONCAT_WS(' ', c.prefix, c.name) AS name,
        COALESCE(SUM(i.total_amount), 0) AS total_invoice,
        COALESCE(SUM(p.amount), 0) AS total_payment
    FROM customers c
    LEFT JOIN invoices i ON c.id = i.customer_id
    LEFT JOIN payments p ON c.id = p.customer_id
    WHERE CONCAT_WS(' ', c.prefix, c.name) LIKE '%$search%'
    GROUP BY c.id
    ORDER BY c.name ASC
    LIMIT $limit OFFSET $offset
";

$results = $conn->query($query);
?>

<table class="table table-hover align-middle mb-0">
    <thead>
        <tr>
            <th>Customer</th>
            <th>Total Invoice (₹)</th>
            <th>Total Payment (₹)</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($results->num_rows > 0): ?>
        <?php while ($row = $results->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= number_format($row['total_invoice'], 2) ?></td>
                <td><?= number_format($row['total_payment'], 2) ?></td>
                <td>
                    <a href="../customer_details.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">View Details</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">No customers found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
