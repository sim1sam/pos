<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config.php';
$company_name = "Your Company";

$result = $conn->query("SELECT name FROM company_profile LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $company_name = $row['name'];
}
?>

</main> <!-- closes main container -->

<footer class="bg-light border-top py-3 mt-auto">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
        <div>
            &copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>
        </div>
        <div>
            Developed with ❤️ by Siemon
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
