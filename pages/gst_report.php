<?php
require_once '../config.php';
include_once '../includes/header.php';

// Default date range (current month)
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

// Get filter parameters
$from_date = isset($_GET['from']) && !empty($_GET['from']) ? $_GET['from'] : $first_day_of_month;
$to_date = isset($_GET['to']) && !empty($_GET['to']) ? $_GET['to'] : $today;
$filter_customer = isset($_GET['customer_id']) && !empty($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

// Get all customers for the filter dropdown
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");

// Build the query for invoice headers
$query = "
    SELECT 
        i.id,
        i.invoice_no,
        i.invoice_date,
        i.due_date,
        i.gst_type,
        i.status,
        i.total_amount,
        c.name AS customer_name,
        c.gstin AS customer_gst
    FROM 
        invoices i
    LEFT JOIN 
        customers c ON i.customer_id = c.id
    WHERE 
        i.is_gst_invoice = 1
        AND i.invoice_date BETWEEN ? AND ?
";

$params = [$from_date, $to_date];
$types = "ss";

if ($filter_customer) {
    $query .= " AND i.customer_id = ?";
    $params[] = $filter_customer;
    $types .= "i";
}

if ($filter_status) {
    $query .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query .= " ORDER BY i.invoice_date DESC, i.id DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result();

// Store all invoice data in an array and calculate tax totals
$invoices_data = [];
$total_amount = 0;
$total_cgst = 0;
$total_sgst = 0;
$total_igst = 0;

while ($row = $invoices->fetch_assoc()) {
    $invoices_data[] = $row;
    $total_amount += $row['total_amount'];
    
    // Get tax totals for this invoice
    $tax_query = "SELECT 
        SUM(cgst_amount) as invoice_cgst,
        SUM(sgst_amount) as invoice_sgst,
        SUM(igst_amount) as invoice_igst
    FROM invoice_details
    WHERE invoice_id = ?";
    
    $tax_stmt = $conn->prepare($tax_query);
    $tax_stmt->bind_param("i", $row['id']);
    $tax_stmt->execute();
    $tax_result = $tax_stmt->get_result();
    
    if ($tax_row = $tax_result->fetch_assoc()) {
        $total_cgst += $tax_row['invoice_cgst'] ?: 0;
        $total_sgst += $tax_row['invoice_sgst'] ?: 0;
        $total_igst += $tax_row['invoice_igst'] ?: 0;
    }
    
    $tax_stmt->close();
}

// Reset the result pointer for later use
$invoices->data_seek(0);

// Get total counts
$total_invoices = count($invoices_data);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">GST Invoice Report</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">GST Invoice Report</li>
    </ol>

    <!-- Filter Form -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All Customers</option>
                <?php $customers->data_seek(0); while ($cust = $customers->fetch_assoc()): ?>
                    <option value="<?= $cust['id'] ?>" <?= ($cust['id'] == $filter_customer ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cust['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="Draft" <?= $filter_status === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Due" <?= $filter_status === 'Due' ? 'selected' : '' ?>>Due</option>
                <option value="Paid" <?= $filter_status === 'Paid' ? 'selected' : '' ?>>Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <a href="gst_report.php" class="btn btn-secondary w-100">Reset</a>
        </div>
    </form>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total GST Invoices</h5>
                    <p class="card-text fs-2"><?= $total_invoices ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Amount</h5>
                    <p class="card-text fs-2"><?= CURRENCY_SYMBOL ?> <?= number_format($total_amount, 2) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total CGST+SGST</h5>
                    <p class="card-text fs-2"><?= CURRENCY_SYMBOL ?> <?= number_format($total_cgst + $total_sgst, 2) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Total IGST</h5>
                    <p class="card-text fs-2"><?= CURRENCY_SYMBOL ?> <?= number_format($total_igst, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Invoices Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            GST Invoices
        </div>
        <div class="card-body">
            <?php if ($invoices->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="gstInvoicesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>HSN/SAC</th>
                                <th>Description</th>
                                <th>Rate</th>
                                <th>Amount</th>
                                <th>CGST</th>
                                <th>SGST</th>
                                <th>IGST</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset the result pointer
                            $invoices->data_seek(0);
                            
                            while ($invoice = $invoices->fetch_assoc()):
                                // Get invoice details for each invoice
                                $details_query = "SELECT 
                                    description,
                                    hsn_sac,
                                    rate,
                                    qty,
                                    amount,
                                    sgst_amount,
                                    cgst_amount,
                                    igst_amount,
                                    total
                                FROM invoice_details 
                                WHERE invoice_id = ?";
                                
                                $stmt = $conn->prepare($details_query);
                                $stmt->bind_param("i", $invoice['id']);
                                $stmt->execute();
                                $details = $stmt->get_result();
                                
                                if ($details->num_rows > 0):
                                    while ($item = $details->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= htmlspecialchars($invoice['invoice_no']) ?></td>
                                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($item['hsn_sac'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= number_format($item['rate'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['amount'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['cgst_amount'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['sgst_amount'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['igst_amount'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['total'], 2) ?></td>
                                </tr>
                            <?php 
                                    endwhile;
                                endif;
                                $stmt->close();
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No GST invoices found for the selected criteria.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Invoice Details -->
    <div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceDetailsModalLabel">Invoice Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="invoiceDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create a separate file for AJAX loading of invoice details -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if (document.getElementById('gstInvoicesTable')) {
        new DataTable('#gstInvoicesTable', {
            order: [[0, 'desc']], // Sort by date descending
            pageLength: 25,
            scrollX: true,
            columnDefs: [
                { className: 'text-nowrap', targets: '_all' }
            ]
        });
    }

    // Handle view details button clicks
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
            
            // Show the modal
            modal.show();
            
            // Load invoice details via AJAX
            fetch(`gst_invoice_details.php?id=${invoiceId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('invoiceDetailsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('invoiceDetailsContent').innerHTML = 
                        `<div class="alert alert-danger">Error loading invoice details: ${error.message}</div>`;
                });
        });
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
