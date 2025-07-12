<?php
require_once '../config.php';

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Invoice ID is required</div>';
    exit;
}

$invoice_id = intval($_GET['id']);

// Get invoice header information
$invoice_query = "
    SELECT 
        i.*,
        c.name AS customer_name,
        c.address AS customer_address,
        c.gst_number AS customer_gst,
        c.phone AS customer_phone,
        c.email AS customer_email
    FROM 
        invoices i
    LEFT JOIN 
        customers c ON i.customer_id = c.id
    WHERE 
        i.id = ? AND i.is_gst_invoice = 1
";

$stmt = $conn->prepare($invoice_query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice_result = $stmt->get_result();

if ($invoice_result->num_rows === 0) {
    echo '<div class="alert alert-danger">GST Invoice not found</div>';
    exit;
}

$invoice = $invoice_result->fetch_assoc();

// Get invoice details (line items)
$details_query = "
    SELECT 
        id,
        description,
        hsn_sac,
        rate,
        qty,
        unit,
        discount,
        amount,
        sgst_rate,
        sgst_amount,
        cgst_rate,
        cgst_amount,
        igst_rate,
        igst_amount,
        total
    FROM 
        invoice_details
    WHERE 
        invoice_id = ?
    ORDER BY 
        id ASC
";

$stmt = $conn->prepare($details_query);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$details_result = $stmt->get_result();

// Get company profile
$company_query = "SELECT * FROM company_profile LIMIT 1";
$company_result = $conn->query($company_query);
$company = $company_result->fetch_assoc();
?>

<div class="container-fluid p-3">
    <!-- Invoice Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h4>GST Invoice #<?= htmlspecialchars($invoice['invoice_no']) ?></h4>
            <p>
                <strong>Date:</strong> <?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?><br>
                <strong>Due Date:</strong> <?= $invoice['due_date'] ? date('d-m-Y', strtotime($invoice['due_date'])) : 'N/A' ?><br>
                <strong>Status:</strong> 
                <span class="badge bg-<?= $invoice['status'] == 'Paid' ? 'success' : ($invoice['status'] == 'Due' ? 'warning' : 'secondary') ?>">
                    <?= htmlspecialchars($invoice['status']) ?>
                </span>
            </p>
        </div>
        <div class="col-md-6 text-md-end">
            <h5><?= htmlspecialchars($company['name'] ?? 'Company Name') ?></h5>
            <p>
                <?= nl2br(htmlspecialchars($company['address'] ?? 'Company Address')) ?><br>
                <strong>GSTIN:</strong> <?= htmlspecialchars($company['gst_number'] ?? 'N/A') ?><br>
                <strong>Phone:</strong> <?= htmlspecialchars($company['phone'] ?? 'N/A') ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($company['email'] ?? 'N/A') ?>
            </p>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <h6><?= htmlspecialchars($invoice['customer_name']) ?></h6>
                    <p>
                        <?= nl2br(htmlspecialchars($invoice['customer_address'] ?? 'N/A')) ?><br>
                        <strong>GSTIN:</strong> <?= htmlspecialchars($invoice['customer_gst'] ?? 'N/A') ?><br>
                        <strong>Phone:</strong> <?= htmlspecialchars($invoice['customer_phone'] ?? 'N/A') ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($invoice['customer_email'] ?? 'N/A') ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">GST Information</h5>
                </div>
                <div class="card-body">
                    <p>
                        <strong>GST Type:</strong> <?= htmlspecialchars($invoice['gst_type'] == 'same' ? 'Intra-State (CGST + SGST)' : 'Inter-State (IGST)') ?><br>
                        <strong>Place of Supply:</strong> <?= htmlspecialchars($invoice['gst_type'] == 'same' ? 'Same State' : 'Different State') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Items -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Invoice Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th>HSN/SAC</th>
                            <th>Rate</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Amount</th>
                            <?php if ($invoice['gst_type'] == 'same'): ?>
                                <th>CGST Rate</th>
                                <th>CGST Amount</th>
                                <th>SGST Rate</th>
                                <th>SGST Amount</th>
                            <?php else: ?>
                                <th>IGST Rate</th>
                                <th>IGST Amount</th>
                            <?php endif; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        $total_taxable = 0;
                        $total_cgst = 0;
                        $total_sgst = 0;
                        $total_igst = 0;
                        $grand_total = 0;
                        
                        while ($item = $details_result->fetch_assoc()): 
                            $total_taxable += $item['amount'];
                            $total_cgst += $item['cgst_amount'];
                            $total_sgst += $item['sgst_amount'];
                            $total_igst += $item['igst_amount'];
                            $grand_total += $item['total'];
                        ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['hsn_sac'] ?: 'N/A') ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['rate'], 2) ?></td>
                                <td><?= $item['qty'] ?></td>
                                <td><?= htmlspecialchars($item['unit'] ?: 'Nos') ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['amount'], 2) ?></td>
                                <?php if ($invoice['gst_type'] == 'same'): ?>
                                    <td><?= $item['cgst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['cgst_amount'], 2) ?></td>
                                    <td><?= $item['sgst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['sgst_amount'], 2) ?></td>
                                <?php else: ?>
                                    <td><?= $item['igst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['igst_amount'], 2) ?></td>
                                <?php endif; ?>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($item['total'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total:</td>
                            <td><?= CURRENCY_SYMBOL ?> <?= number_format($total_taxable, 2) ?></td>
                            <?php if ($invoice['gst_type'] == 'same'): ?>
                                <td></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($total_cgst, 2) ?></td>
                                <td></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($total_sgst, 2) ?></td>
                            <?php else: ?>
                                <td></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($total_igst, 2) ?></td>
                            <?php endif; ?>
                            <td><?= CURRENCY_SYMBOL ?> <?= number_format($grand_total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- GST Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">GST Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>HSN/SAC</th>
                            <th>Taxable Amount</th>
                            <?php if ($invoice['gst_type'] == 'same'): ?>
                                <th>CGST Rate</th>
                                <th>CGST Amount</th>
                                <th>SGST Rate</th>
                                <th>SGST Amount</th>
                            <?php else: ?>
                                <th>IGST Rate</th>
                                <th>IGST Amount</th>
                            <?php endif; ?>
                            <th>Total Tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset the result pointer
                        $details_result->data_seek(0);
                        
                        // Group by HSN/SAC code
                        $hsn_summary = [];
                        
                        while ($item = $details_result->fetch_assoc()) {
                            $hsn = $item['hsn_sac'] ?: 'N/A';
                            
                            if (!isset($hsn_summary[$hsn])) {
                                $hsn_summary[$hsn] = [
                                    'taxable' => 0,
                                    'cgst_rate' => $item['cgst_rate'],
                                    'cgst_amount' => 0,
                                    'sgst_rate' => $item['sgst_rate'],
                                    'sgst_amount' => 0,
                                    'igst_rate' => $item['igst_rate'],
                                    'igst_amount' => 0
                                ];
                            }
                            
                            $hsn_summary[$hsn]['taxable'] += $item['amount'];
                            $hsn_summary[$hsn]['cgst_amount'] += $item['cgst_amount'];
                            $hsn_summary[$hsn]['sgst_amount'] += $item['sgst_amount'];
                            $hsn_summary[$hsn]['igst_amount'] += $item['igst_amount'];
                        }
                        
                        foreach ($hsn_summary as $hsn => $summary):
                            $total_tax = $summary['cgst_amount'] + $summary['sgst_amount'] + $summary['igst_amount'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($hsn) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($summary['taxable'], 2) ?></td>
                                <?php if ($invoice['gst_type'] == 'same'): ?>
                                    <td><?= $summary['cgst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($summary['cgst_amount'], 2) ?></td>
                                    <td><?= $summary['sgst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($summary['sgst_amount'], 2) ?></td>
                                <?php else: ?>
                                    <td><?= $summary['igst_rate'] ?>%</td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= number_format($summary['igst_amount'], 2) ?></td>
                                <?php endif; ?>
                                <td><?= CURRENCY_SYMBOL ?> <?= number_format($total_tax, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row">
        <div class="col-md-12 text-end">
            <a href="invoice_pdf.php?id=<?= $invoice_id ?>&type=gst" class="btn btn-primary" target="_blank">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
            <a href="invoice_view.php?id=<?= $invoice_id ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> Full View
            </a>
        </div>
    </div>
</div>
