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
$hsn_summary = isset($_GET['hsn_summary']) && $_GET['hsn_summary'] == '1';

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
            <button type="submit" class="btn btn-primary w-100">Generate Report</button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="hsn_summary" value="1" class="btn btn-info w-100">HSN/SAC Summary</button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
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
    <?php if ($invoices->num_rows > 0 && !$hsn_summary): ?>
        <div class="table-responsive mt-4">
            <table class="table table-bordered table-striped" id="gst-invoices-table">
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
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($item['amount']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($item['cgst_amount']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($item['sgst_amount']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($item['igst_amount']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($item['total']), 0) ?></td>
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
            <?php elseif ($invoices->num_rows > 0 && $hsn_summary): ?>
                <!-- HSN/SAC Summary Report -->
                <h4 class="mt-3 mb-3">HSN/SAC Summary Report (<?= date('d-m-Y', strtotime($from_date)) ?> to <?= date('d-m-Y', strtotime($to_date)) ?>)</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="hsn-summary-table">
                        <thead>
                            <tr>
                                <th>HSN/SAC</th>
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
                            // Query to get HSN/SAC summary
                            $hsn_query = "SELECT 
                                id.hsn_sac,
                                MAX(id.description) as description,
                                SUM(id.amount) as total_amount,
                                SUM(id.cgst_amount) as total_cgst,
                                SUM(id.sgst_amount) as total_sgst,
                                SUM(id.igst_amount) as total_igst,
                                SUM(id.total) as grand_total
                            FROM 
                                invoice_details id
                            JOIN 
                                invoices i ON id.invoice_id = i.id
                            WHERE 
                                i.is_gst_invoice = 1
                                AND i.invoice_date BETWEEN ? AND ?";
                            
                            if ($filter_customer) {
                                $hsn_query .= " AND i.customer_id = ?";
                            }
                            
                            if ($filter_status) {
                                $hsn_query .= " AND i.status = ?";
                            }
                            
                            $hsn_query .= " GROUP BY id.hsn_sac ORDER BY id.hsn_sac";
                            
                            $hsn_stmt = $conn->prepare($hsn_query);
                            
                            if ($filter_customer && $filter_status) {
                                $hsn_stmt->bind_param("ssis", $from_date, $to_date, $filter_customer, $filter_status);
                            } elseif ($filter_customer) {
                                $hsn_stmt->bind_param("ssi", $from_date, $to_date, $filter_customer);
                            } elseif ($filter_status) {
                                $hsn_stmt->bind_param("sss", $from_date, $to_date, $filter_status);
                            } else {
                                $hsn_stmt->bind_param("ss", $from_date, $to_date);
                            }
                            
                            $hsn_stmt->execute();
                            $hsn_result = $hsn_stmt->get_result();
                            
                            $total_taxable = 0;
                            $total_cgst = 0;
                            $total_sgst = 0;
                            $total_igst = 0;
                            $total_amount = 0;
                            
                            while ($hsn = $hsn_result->fetch_assoc()):
                                // Get GST rate from gst_config table
                                $gst_query = "SELECT gst_rate FROM gst_config WHERE hsn_sac = ? LIMIT 1";
                                $gst_stmt = $conn->prepare($gst_query);
                                $gst_stmt->bind_param("s", $hsn['hsn_sac']);
                                $gst_stmt->execute();
                                $gst_result = $gst_stmt->get_result();
                                $gst_rate = 0;
                                
                                if ($gst_row = $gst_result->fetch_assoc()) {
                                    $gst_rate = $gst_row['gst_rate'];
                                }
                                $gst_stmt->close();
                                
                                $total_taxable += $hsn['total_amount'];
                                $total_cgst += $hsn['total_cgst'];
                                $total_sgst += $hsn['total_sgst'];
                                $total_igst += $hsn['total_igst'];
                                $total_amount += $hsn['grand_total'];
                            ?>
                                <?php if (!empty($hsn['hsn_sac'])): ?>
                                <tr>
                                    <td><?= htmlspecialchars($hsn['hsn_sac']) ?></td>
                                    <td><?= number_format($gst_rate, 2) ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($hsn['total_amount']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($hsn['total_cgst']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($hsn['total_sgst']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($hsn['total_igst']), 0) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($hsn['grand_total']), 0) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                            <tr class="table-dark fw-bold">
                                <td colspan="2" class="text-end">Total:</td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($total_taxable), 0) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($total_cgst), 0) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($total_sgst), 0) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($total_igst), 0) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format(round($total_amount), 0) ?></td>
                            </tr>
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
    // Initialize DataTable for detailed GST report
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
    
    // Initialize DataTable for HSN/SAC summary report
    if (document.getElementById('hsn-summary-table')) {
        new DataTable('#hsn-summary-table', {
            order: [[0, 'asc']], // Sort by HSN/SAC ascending
            pageLength: 50,
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
