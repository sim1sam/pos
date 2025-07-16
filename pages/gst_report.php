<?php
require_once '../config.php';
require_once '../includes/header.php';

// Function to clean numeric values and remove the 262145 prefix
function clean_number($value) {
    if (is_null($value) || $value === '') {
        return 0;
    }
    // First check if the value contains the problematic prefix with space
    if (is_string($value) && strpos($value, '262145 ') === 0) {
        $value = substr($value, 7); // Remove the first 7 characters (262145 with space)
    }
    // Also check for the prefix without space
    else if (is_string($value) && strpos($value, '262145') === 0) {
        $value = substr($value, 6); // Remove the first 6 characters (262145)
    }
    // Remove any remaining non-numeric characters except decimal point
    $cleaned = preg_replace('/[^0-9.]/', '', $value);
    return floatval($cleaned);
}

// Function to display numeric values safely without the 262145 prefix
function display_number($value, $decimals = 0) {
    // Convert to string to handle any type
    $value = (string)$value;
    
    // Check for and remove the 262145 prefix with space
    if (strpos($value, '262145 ') === 0) {
        $value = substr($value, 7);
    }
    // Check for and remove the 262145 prefix without space
    else if (strpos($value, '262145') === 0) {
        $value = substr($value, 6);
    }
    
    // Format the number
    return number_format(floatval($value), $decimals);
}

// Function to strip 262145 prefix from all fields in an array
function strip_prefix_from_array(&$data) {
    if (!is_array($data)) return;
    
    foreach ($data as $key => &$value) {
        if (is_string($value)) {
            // Check for '262145 ' pattern (with space)
            if (strpos($value, '262145 ') === 0) {
                $value = substr($value, 7); // Remove '262145 ' (with space)
            }
            // Also check for '262145' without space
            else if (strpos($value, '262145') === 0) {
                $value = substr($value, 6); // Remove '262145' (without space)
            }
        } else if (is_array($value)) {
            strip_prefix_from_array($value);
        }
    }
}

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
    // Strip the 262145 prefix from all fields in the row
    strip_prefix_from_array($row);
    $invoices_data[] = $row;
    // Clean and convert total amount to prevent the 262145 prefix issue
    $clean_amount = isset($row['total_amount']) ? floatval(preg_replace('/[^0-9.]/', '', $row['total_amount'])) : 0;
    $total_amount += $clean_amount;
    
    // Get tax totals for this invoice
    $tax_query = "SELECT 
        CAST(TRIM(REPLACE(REPLACE(SUM(cgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as invoice_cgst,
        CAST(TRIM(REPLACE(REPLACE(SUM(sgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as invoice_sgst,
        CAST(TRIM(REPLACE(REPLACE(SUM(igst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as invoice_igst
    FROM invoice_details
    WHERE invoice_id = ?";
    
    $tax_stmt = $conn->prepare($tax_query);
    $tax_stmt->bind_param("i", $row['id']);
    $tax_stmt->execute();
    $tax_result = $tax_stmt->get_result();
    
    if ($tax_row = $tax_result->fetch_assoc()) {
        // Strip the 262145 prefix from all fields in the tax row
        strip_prefix_from_array($tax_row);
        
        // Clean and convert tax values to prevent the 262145 prefix issue
        $clean_cgst = isset($tax_row['invoice_cgst']) ? floatval(preg_replace('/[^0-9.]/', '', $tax_row['invoice_cgst'])) : 0;
        $clean_sgst = isset($tax_row['invoice_sgst']) ? floatval(preg_replace('/[^0-9.]/', '', $tax_row['invoice_sgst'])) : 0;
        $clean_igst = isset($tax_row['invoice_igst']) ? floatval(preg_replace('/[^0-9.]/', '', $tax_row['invoice_igst'])) : 0;
        
        $total_cgst += $clean_cgst;
        $total_sgst += $clean_sgst;
        $total_igst += $clean_igst;
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
    
    <!-- Print-specific styles -->
    <style>
.print-container { display: none !important; visibility: hidden !important; position: absolute !important; left: -9999px !important; }
    /* Make sure print-table is visible and styled when printing */
    .print-table { visibility: visible !important; display: table !important; width: 100% !important; }
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 4px !important; }
    
    @media print {
            /* Hide everything by default */
            #layoutSidenav_nav, #layoutSidenav_content > nav, .card-header, .no-print, .dataTables_length, 
            .dataTables_filter, .dataTables_paginate, .pagination, button, .card-footer, .app-footer,
            form, .breadcrumb, .dataTables_info {
                display: none !important;
            }
            
            /* Hide non-print elements but keep containers visible */
            h1, .card, .table-responsive, .print-section {
                display: none !important;
            }
            
            /* Keep essential containers visible */
            body, #layoutSidenav_content, .container-fluid, .row, .col, .card-body {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Show only print container */
            .print-container {
                display: block !important;
                width: 100% !important;
                visibility: visible !important;
                position: relative !important; /* Changed from absolute to relative */
                left: 0 !important;
                top: 0 !important;
                z-index: 9999 !important;
                overflow: visible !important;
                height: auto !important; /* Ensure height is based on content */
            }
            
            /* Basic print styling */
            body {
                margin: 5mm !important;
                padding: 0 !important;
                font-family: 'Trebuchet MS', sans-serif;
            }
            
            /* Table styling */
            .print-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 10px !important;
            }
            
            .print-table th {
                background-color: #f5f5f5 !important;
                font-weight: bold !important;
                border-bottom: 1px solid #ddd !important;
                padding: 4px !important;
            }
            
            .print-table td {
                padding: 3px !important;
                border-bottom: 1px solid #eee !important;
            }
            
            /* Repeat table headers on each page */
            thead {
                display: table-header-group !important;
            }
            
            /* Avoid page breaks inside rows */
            tr {
                page-break-inside: avoid !important;
            }
            
            /* Ensure landscape orientation */
            @page {
                margin: 5mm;
                size: landscape !important;
                counter-increment: page;
            }
            
            html, body {
                width: 100% !important;
                height: 100% !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .page-number:after {
                content: counter(page);
            }
            
            /* Company header */
            .company-header {
                margin-bottom: 5px;
            }
        }
    </style>

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
                    <p class="card-text fs-4"><?= CURRENCY_SYMBOL ?> <?= display_number($total_amount) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total CGST+SGST</h5>
                    <p class="card-text fs-4"><?= CURRENCY_SYMBOL ?> <?= display_number($total_cgst + $total_sgst) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Total IGST</h5>
                    <p class="card-text fs-4"><?= CURRENCY_SYMBOL ?> <?= display_number($total_igst) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Invoices Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <i class="fas fa-table me-1"></i>
        GST Invoices
    </div>
    <!-- Removed duplicate print button here -->
    <!-- Only the print button above the table remains -->

            
            <script>

                
                // Print flag to prevent multiple calls
                let isPrinting = false;
                
                // Consolidated print function
                function printReport(isHsnSummary = false) {
                    // Prevent multiple calls
                    if (isPrinting) return;
                    isPrinting = true;
                    
                    // Get the print container element
                    const printContainer = document.querySelector('.print-container');
                    
                    // Update the report type text in the header
                    // Find the report type element by iterating through paragraphs
                    const paragraphs = printContainer.querySelectorAll('p');
                    let reportTypeElement = null;
                    
                    for (let i = 0; i < paragraphs.length; i++) {
                        if (paragraphs[i].textContent.includes('Report Type:')) {
                            reportTypeElement = paragraphs[i];
                            break;
                        }
                    }
                    
                    if (reportTypeElement) {
                        if (isHsnSummary) {
                            reportTypeElement.innerHTML = '<strong>Report Type:</strong> HSN/SAC Summary';
                        } else {
                            reportTypeElement.innerHTML = '<strong>Report Type:</strong> Detailed GST Invoice';
                        }
                    }
                    
                    // Show/hide appropriate tables based on report type
                    const tables = printContainer.querySelectorAll('.print-table');
                    
                    for (let i = 0; i < tables.length; i++) {
                        const firstHeader = tables[i].querySelector('thead th:first-child');
                        const isHsnTable = firstHeader && firstHeader.textContent.trim() === 'HSN/SAC';
                        
                        if (isHsnSummary) {
                            tables[i].style.display = isHsnTable ? 'table' : 'none';
                        } else {
                            tables[i].style.display = isHsnTable ? 'none' : 'table';
                        }
                    }
                    
                    // Show the print container
                    printContainer.style.display = 'block';
                    printContainer.style.visibility = 'visible';
                    printContainer.style.position = 'fixed';
                    printContainer.style.top = '0';
                    printContainer.style.left = '0';
                    printContainer.style.zIndex = '9999';
                    printContainer.style.width = '100%';
                    printContainer.style.height = 'auto';
                    printContainer.style.background = '#fff';
                    
                    // Move print container to the body to avoid inheritance issues
                    document.body.appendChild(printContainer);
                    
                    // Force landscape mode and ensure print container is visible
                    const style = document.createElement('style');
                    style.innerHTML = '@page { size: landscape !important; margin: 0.5cm; } ' +
                                     '@media print { ' +
                                     '  html, body { overflow: visible !important; height: auto !important; margin: 0 !important; padding: 0 !important; } ' +
                                     '  .print-container { display: block !important; visibility: visible !important; position: relative !important; left: 0 !important; top: 0 !important; overflow: visible !important; page-break-after: avoid !important; } ' +
                                     '  .print-container * { visibility: visible !important; } ' +
                                     '  body > *:not(.print-container) { display: none !important; } ' +
                                     '  .print-table { width: 100% !important; border-collapse: collapse !important; page-break-inside: auto !important; } ' +
                                     '  .print-table thead { display: table-header-group; } ' +
                                     '  .print-table tr { page-break-inside: avoid !important; page-break-after: auto !important; } ' +
                                     '  .print-table th, .print-table td { border: 1px solid #000 !important; padding: 4px !important; } ' +
                                     '  @page { margin: 0.5cm; } ' +
                                     '  h2.report-title { display: block; text-align: center; font-size: 18px; margin-bottom: 10px; } ' +
                                     '  html { height: auto !important; } ' +
                                     '}'; 
                    style.id = 'forceLandscape';
                    document.head.appendChild(style);
                    
                    // Update the report title based on the report type
                    const reportTitleElement = printContainer.querySelector('h2');
                    if (reportTitleElement) {
                        reportTitleElement.textContent = isHsnSummary ? 'HSN/SAC Summary Report' : 'GST Invoice Report';
                        reportTitleElement.classList.add('report-title');
                    }
                    
                    // Trigger print
                    window.print();
                    
                    // Reset flag and restore view after print dialog closes
                    // Note: This will happen whether print is completed or cancelled
                    window.addEventListener('afterprint', function resetPrintView() {
                        // Remove this one-time event listener
                        window.removeEventListener('afterprint', resetPrintView);
                        
                        // Reset printing flag
                        isPrinting = false;
                        
                        // Hide the print container
                        printContainer.style.display = 'none';
                        printContainer.style.visibility = 'hidden';
                        printContainer.style.position = 'absolute';
                        printContainer.style.left = '-9999px';
                        
                        // Remove the temporary style
                        const landscapeStyle = document.getElementById('forceLandscape');
                        if (landscapeStyle) landscapeStyle.remove();
                        
                        // Return print container to its original position if needed
                        const originalContainer = document.querySelector('.container-fluid');
                        if (originalContainer) {
                            originalContainer.appendChild(printContainer);
                        }
                    });
                    
                    // Fallback timeout in case afterprint event doesn't fire
                    // This ensures the print state is always reset, even if the browser doesn't support afterprint
                    setTimeout(function() {
                        if (isPrinting) {
                            console.log('Print timeout reached, resetting print state');
                            
                            // Reset printing flag
                            isPrinting = false;
                            
                            // Hide the print container
                            printContainer.style.display = 'none';
                            printContainer.style.visibility = 'hidden';
                            printContainer.style.position = 'absolute';
                            printContainer.style.left = '-9999px';
                            
                            // Remove the temporary style
                            const landscapeStyle = document.getElementById('forceLandscape');
                            if (landscapeStyle) landscapeStyle.remove();
                            
                            // Return print container to its original position if needed
                            const originalContainer = document.querySelector('.container-fluid');
                            if (originalContainer) {
                                originalContainer.appendChild(printContainer);
                            }
                        }
                    }, 5000); // 5 second timeout
                }
                
                // Remove the global beforeprint handler to avoid conflicts
                window.removeEventListener('beforeprint', function() {
                    printReport();
                });
                
                // Remove the global afterprint handler to avoid conflicts
                window.removeEventListener('afterprint', function() {
                    const printContainer = document.querySelector('.print-container');
                    printContainer.style.display = 'none';
                    printContainer.style.visibility = 'hidden';
                    printContainer.style.position = 'absolute';
                    printContainer.style.left = '-9999px';
                });
            </script>
        </div>
        <div class="card-body">
    <!-- Regular display tables (non-print) -->
    <?php if ($invoices->num_rows > 0 && !$hsn_summary): ?>
        <!-- Single GST Invoices Table with Print Button -->
        <div class="table-responsive mt-4">
            <div class="d-flex justify-content-between mb-3">
                <h4>GST Invoices</h4>
                <!-- Print button -->
                <button onclick="printReport()" class="btn btn-primary btn-sm">🖨️ Print Report</button>
            </div>
            <div id="single-table-container">
                <!-- The table will be generated via JavaScript below -->
            </div>
            
            <script>
                $(document).ready(function() {
                    // Initialize DataTable with empty data first
                    const table = $('#single-table-container').html('<table id="gst-invoices-table" class="table table-bordered table-striped"></table>');
                    $('#gst-invoices-table').DataTable({
                        "ordering": true,
                        "searching": true,
                        "paging": true,
                        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                        "info": true,
                        "columns": [
                            { "title": "Date" },
                            { "title": "Invoice #" },
                            { "title": "Customer" },
                            { "title": "HSN/SAC" },
                            { "title": "Description" },
                            { "title": "Rate" },
                            { "title": "Amount" },
                            { "title": "CGST" },
                            { "title": "SGST" },
                            { "title": "IGST" },
                            { "title": "Total" }
                        ],
                        "data": [
                            <?php
                            $invoices->data_seek(0);
                            while ($invoice = $invoices->fetch_assoc()):
                                $details_query = "SELECT 
                                    description,
                                    hsn_sac,
                                    rate,
                                    qty,
                                    CAST(TRIM(REPLACE(REPLACE(amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as amount,
                                    CAST(TRIM(REPLACE(REPLACE(sgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as sgst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(cgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as cgst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(igst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as igst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(total, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total
                                FROM invoice_details 
                                WHERE invoice_id = ?";
                                $stmt = $conn->prepare($details_query);
                                $stmt->bind_param("i", $invoice['id']);
                                $stmt->execute();
                                $details = $stmt->get_result();
                                if ($details->num_rows > 0):
                                    while ($item = $details->fetch_assoc()):
                                        strip_prefix_from_array($item);
                                        
                                        // Format values for JSON output
                                        $date = date('d-m-Y', strtotime($invoice['invoice_date']));
                                        $invoice_no = addslashes($invoice['invoice_no']);
                                        $customer = addslashes($invoice['customer_name']);
                                        $hsn_sac = addslashes($item['hsn_sac'] ?? 'N/A');
                                        $description = addslashes($item['description']);
                                        $rate = number_format($item['rate'], 2);
                                        $amount = CURRENCY_SYMBOL . ' ' . display_number($item['amount']);
                                        $cgst = CURRENCY_SYMBOL . ' ' . display_number($item['cgst_amount']);
                                        $sgst = CURRENCY_SYMBOL . ' ' . display_number($item['sgst_amount']);
                                        $igst = CURRENCY_SYMBOL . ' ' . display_number($item['igst_amount']);
                                        $total = CURRENCY_SYMBOL . ' ' . display_number($item['total']);
                            ?>
                            ["<?= $date ?>", "<?= $invoice_no ?>", "<?= $customer ?>", "<?= $hsn_sac ?>", "<?= $description ?>", "<?= $rate ?>", "<?= $amount ?>", "<?= $cgst ?>", "<?= $sgst ?>", "<?= $igst ?>", "<?= $total ?>"],
                            <?php
                                    endwhile;
                                endif;
                                $stmt->close();
                            endwhile;
                            ?>
                        ]
                    });
                });
            </script>
        </div>
    <?php elseif ($invoices->num_rows > 0 && $hsn_summary): ?>
        <!-- HSN/SAC Summary Report -->
        <!-- First tile heading removed as requested -->
        <div class="table-responsive print-section" id="hsn-summary-container">
            <div id="hsn-summary-table-wrapper"></div>
            
            <script>
                $(document).ready(function() {
                    // Get HSN summary data
                    const hsnData = [
                        <?php
                        // Query to get HSN/SAC summary
                        $hsn_query = "SELECT 
                            id.hsn_sac,
                            MAX(id.description) as description,
                            SUM(id.qty) as total_qty,
                            CAST(TRIM(REPLACE(REPLACE(SUM(id.amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_amount,
                            CAST(TRIM(REPLACE(REPLACE(SUM(id.cgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_cgst,
                            CAST(TRIM(REPLACE(REPLACE(SUM(id.sgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_sgst,
                            CAST(TRIM(REPLACE(REPLACE(SUM(id.igst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_igst,
                            CAST(TRIM(REPLACE(REPLACE(SUM(id.total), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as grand_total
                        FROM 
                            invoice_details id
                        JOIN 
                            invoices i ON id.invoice_id = i.id
                        WHERE 
                            i.is_gst_invoice = 1
                             AND i.invoice_date BETWEEN ? AND ?";
                         
                        $params = [$from_date, $to_date];
                        $types = "ss";
                         
                        if ($filter_customer) {
                            $hsn_query .= " AND i.customer_id = ?";
                            $params[] = $filter_customer;
                            $types .= "i";
                        }
                        
                        if ($filter_status) {
                            $hsn_query .= " AND i.status = ?";
                            $params[] = $filter_status;
                            $types .= "s";
                        }
                         
                        $hsn_query .= " GROUP BY id.hsn_sac ORDER BY id.hsn_sac";
                         
                        $hsn_stmt = $conn->prepare($hsn_query);
                        $hsn_stmt->bind_param($types, ...$params);
                        $hsn_stmt->execute();
                        $hsn_result = $hsn_stmt->get_result();
                         
                        $total_hsn_amount = 0;
                        $total_hsn_cgst = 0;
                        $total_hsn_sgst = 0;
                        $total_hsn_igst = 0;
                        $total_hsn_grand = 0;
                         
                        while ($hsn = $hsn_result->fetch_assoc()):
                            strip_prefix_from_array($hsn);
                            
                            $total_hsn_amount += floatval($hsn['total_amount']);
                            $total_hsn_cgst += floatval($hsn['total_cgst']);
                            $total_hsn_sgst += floatval($hsn['total_sgst']);
                            $total_hsn_igst += floatval($hsn['total_igst']);
                            $total_hsn_grand += floatval($hsn['grand_total']);
                            
                            // Format for DataTable
                            $formatted_qty = display_number($hsn['total_qty']);
                            $formatted_amount = CURRENCY_SYMBOL . ' ' . display_number($hsn['total_amount']);
                            $formatted_cgst = CURRENCY_SYMBOL . ' ' . display_number($hsn['total_cgst']);
                            $formatted_sgst = CURRENCY_SYMBOL . ' ' . display_number($hsn['total_sgst']);
                            $formatted_igst = CURRENCY_SYMBOL . ' ' . display_number($hsn['total_igst']);
                            $formatted_total = CURRENCY_SYMBOL . ' ' . display_number($hsn['grand_total']);
                            
                            echo "[\n";
                            echo "    \"" . addslashes($hsn['hsn_sac']) . "\",\n";
                            echo "    \"" . addslashes($hsn['description']) . "\",\n";
                            echo "    \"$formatted_qty\",\n";
                            echo "    \"$formatted_amount\",\n";
                            echo "    \"$formatted_cgst\",\n";
                            echo "    \"$formatted_sgst\",\n";
                            echo "    \"$formatted_igst\",\n";
                            echo "    \"$formatted_total\"\n";
                            echo "],\n";
                        endwhile;
                        $hsn_stmt->close();
                        ?>
                    ];
                    
                    // Create table element
                    const tableElement = document.createElement('table');
                    tableElement.id = 'hsn-summary-table';
                    tableElement.className = 'table table-bordered table-striped';
                    document.getElementById('hsn-summary-table-wrapper').appendChild(tableElement);
                    
                    // Initialize DataTable
                    $('#hsn-summary-table').DataTable({
                        "processing": true,
                        "serverSide": false,
                        "paging": true,
                        "ordering": true,
                        "info": true,
                        "searching": true,
                        "data": hsnData,
                        "columns": [
                            { "title": "HSN/SAC" },
                            { "title": "Description" },
                            { "title": "QTY" },
                            { "title": "Amount" },
                            { "title": "CGST" },
                            { "title": "SGST" },
                            { "title": "IGST" },
                            { "title": "Total" }
                        ]
                    });
                });
            </script>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No invoices found matching the selected criteria.</div>
    <?php endif; ?>
        </div>
    </div>
</div>

<!-- Print Container (Moved outside card structure for better printing, hidden in normal view) -->
<div class="print-container" style="display: none;">
        <?php
        // Get company information for print header
        $company_query = "SELECT * FROM company_profile LIMIT 1";
        $company_result = $conn->query($company_query);
        $company = $company_result->fetch_assoc();
        
        // Strip prefix from company data
        strip_prefix_from_array($company);
        ?>
        
        <!-- Print Header with Company Info -->
        <div class="row mb-3 company-header">
            <div class="col-6">
                <?php if (!empty($company['logo'])): ?>
                <img src="../uploads/<?= htmlspecialchars($company['logo']) ?>" alt="Company Logo" style="max-height: 60px; max-width: 200px;">
                <?php endif; ?>
                <h3 style="margin: 5px 0; font-size: 16px;"><?= htmlspecialchars($company['name'] ?? '') ?></h3>
                <p style="margin: 0; font-size: 11px;"><?= nl2br(htmlspecialchars($company['address'] ?? '')) ?></p>
                <?php if (!empty($company['phone'])): ?>
                <p style="margin: 0; font-size: 11px;">Phone: <?= htmlspecialchars($company['phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($company['email'])): ?>
                <p style="margin: 0; font-size: 11px;">Email: <?= htmlspecialchars($company['email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($company['gstin'])): ?>
                <p style="margin: 0; font-size: 11px;"><strong>GSTIN:</strong> <?= htmlspecialchars($company['gstin']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h2 style="margin-top: 10px; font-size: 18px;">GST Invoice Report</h2>
                <p style="margin: 0; font-size: 12px;">
                    <strong>Period:</strong> <?= date('d-m-Y', strtotime($from_date)) ?> to <?= date('d-m-Y', strtotime($to_date)) ?>
                </p>
                <?php if ($filter_customer): ?>
                    <?php
                    $customer_name_query = "SELECT name FROM customers WHERE id = ?";
                    $stmt = $conn->prepare($customer_name_query);
                    $stmt->bind_param("i", $filter_customer);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        echo '<p style="margin: 0; font-size: 12px;"><strong>Customer:</strong> ' . htmlspecialchars($row['name']) . '</p>';
                    }
                    $stmt->close();
                    ?>
                <?php endif; ?>
                <?php if ($filter_status): ?>
                    <p style="margin: 0; font-size: 12px;"><strong>Status:</strong> <?= htmlspecialchars($filter_status) ?></p>
                <?php endif; ?>
                <?php if ($hsn_summary): ?>
                    <p style="margin: 0; font-size: 12px;"><strong>Report Type:</strong> HSN/SAC Summary</p>
                <?php else: ?>
                    <p style="margin: 0; font-size: 12px;"><strong>Report Type:</strong> Detailed GST Invoice</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Print Table -->
        <?php if (!$hsn_summary): ?>
        <table class="print-table">
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
            $invoices->data_seek(0);
            
            while ($invoice = $invoices->fetch_assoc()):
                $details_query = "SELECT 
                    description,
                    hsn_sac,
                    rate,
                    qty,
                    CAST(TRIM(REPLACE(REPLACE(amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as amount,
                    CAST(TRIM(REPLACE(REPLACE(sgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as sgst_amount,
                    CAST(TRIM(REPLACE(REPLACE(cgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as cgst_amount,
                    CAST(TRIM(REPLACE(REPLACE(igst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as igst_amount,
                    CAST(TRIM(REPLACE(REPLACE(total, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total
                FROM invoice_details 
                WHERE invoice_id = ?";
                
                $stmt = $conn->prepare($details_query);
                $stmt->bind_param("i", $invoice['id']);
                $stmt->execute();
                $details = $stmt->get_result();
                
                if ($details->num_rows > 0):
                    while ($item = $details->fetch_assoc()):
                        strip_prefix_from_array($item);
            ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></td>
                    <td><?= htmlspecialchars($invoice['invoice_no']) ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td><?= htmlspecialchars($item['hsn_sac'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td><?= number_format($item['rate'], 2) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['amount']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['cgst_amount']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['sgst_amount']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['igst_amount']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['total']) ?></td>
                </tr>
            <?php 
                    endwhile;
                endif;
                $stmt->close();
            endwhile; 
            ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6">Total</th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_amount - $total_cgst - $total_sgst - $total_igst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_cgst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_sgst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_igst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_amount) ?></th>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <!-- HSN Summary Print Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>HSN/SAC</th>
                    <th>Description</th>
                    <th>QTY</th>
                    <th>Amount</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>IGST</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hsn_query = "SELECT 
                    id.hsn_sac,
                    MAX(id.description) as description,
                    SUM(id.qty) as total_qty,
                    CAST(TRIM(REPLACE(REPLACE(SUM(id.amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_amount,
                    CAST(TRIM(REPLACE(REPLACE(SUM(id.cgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_cgst,
                    CAST(TRIM(REPLACE(REPLACE(SUM(id.sgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_sgst,
                    CAST(TRIM(REPLACE(REPLACE(SUM(id.igst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_igst,
                    CAST(TRIM(REPLACE(REPLACE(SUM(id.total), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as grand_total
                FROM 
                    invoice_details id
                JOIN 
                    invoices i ON id.invoice_id = i.id
                WHERE 
                    i.is_gst_invoice = 1
                     AND i.invoice_date BETWEEN ? AND ?";
                 
                $params = [$from_date, $to_date];
                $types = "ss";
                 
                if ($filter_customer) {
                    $hsn_query .= " AND i.customer_id = ?";
                    $params[] = $filter_customer;
                    $types .= "i";
                }
                
                if ($filter_status) {
                    $hsn_query .= " AND i.status = ?";
                    $params[] = $filter_status;
                    $types .= "s";
                }
                 
                $hsn_query .= " GROUP BY id.hsn_sac ORDER BY id.hsn_sac";
                 
                $hsn_stmt = $conn->prepare($hsn_query);
                $hsn_stmt->bind_param($types, ...$params);
                $hsn_stmt->execute();
                $hsn_result = $hsn_stmt->get_result();
                 
                $total_hsn_amount = 0;
                $total_hsn_cgst = 0;
                $total_hsn_sgst = 0;
                $total_hsn_igst = 0;
                $total_hsn_grand = 0;
                 
                while ($hsn = $hsn_result->fetch_assoc()):
                    strip_prefix_from_array($hsn);
                     
                     // Accumulate totals for the HSN/SAC summary
                     $total_hsn_amount += floatval($hsn['total_amount']);
                     $total_hsn_cgst += floatval($hsn['total_cgst']);
                     $total_hsn_sgst += floatval($hsn['total_sgst']);
                     $total_hsn_igst += floatval($hsn['total_igst']);
                     $total_hsn_grand += floatval($hsn['grand_total']);
                 ?>
                <tr>
                    <td><?= htmlspecialchars($hsn['hsn_sac']) ?></td>
                    <td><?= htmlspecialchars($hsn['description']) ?></td>
                    <td><?= display_number($hsn['total_qty']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_amount']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_cgst']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_sgst']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_igst']) ?></td>
                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['grand_total']) ?></td>
                </tr>
                <?php endwhile; 
                $hsn_stmt->close();
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_hsn_amount) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_hsn_cgst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_hsn_sgst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_hsn_igst) ?></th>
                    <th><?= CURRENCY_SYMBOL ?> <?= display_number($total_hsn_grand) ?></th>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
        
        <!-- Print Footer -->
        <div style="margin-top: 10px; padding-top: 5px; border-top: 1px solid #ddd; font-size: 10px; page-break-inside: avoid;">
            <div style="display: flex; justify-content: space-between;">
                <div style="text-align: left;">
                    <p style="margin: 0;">For <?= htmlspecialchars($company['name'] ?? '') ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0;">Authorized Signatory</p>
                </div>
            </div>
            <div class="text-center" style="margin-top: 5px; font-size: 8px;">
                <p style="margin: 0;">Page <span class="page-number"></span></p>
            </div>
        </div>
    </div> <!-- End of print-container -->
    
    <!-- Regular display table (non-print) -->
    <?php if ($invoices->num_rows > 0 && !$hsn_summary): ?>
        <div class="table-responsive mt-4 print-section">
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
                                    CAST(TRIM(REPLACE(REPLACE(amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as amount,
                                    CAST(TRIM(REPLACE(REPLACE(sgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as sgst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(cgst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as cgst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(igst_amount, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as igst_amount,
                                    CAST(TRIM(REPLACE(REPLACE(total, '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total
                                FROM invoice_details 
                                WHERE invoice_id = ?";
                                
                                $stmt = $conn->prepare($details_query);
                                $stmt->bind_param("i", $invoice['id']);
                                $stmt->execute();
                                $details = $stmt->get_result();
                                
                                if ($details->num_rows > 0):
                                    while ($item = $details->fetch_assoc()):
                                        // Strip the 262145 prefix from all fields in the item
                                        strip_prefix_from_array($item);
                            ?>
                                <tr>
                                    <td><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td><?= htmlspecialchars($invoice['invoice_no']) ?></td>
                                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($item['hsn_sac'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= number_format($item['rate'], 2) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['amount']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['cgst_amount']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['sgst_amount']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['igst_amount']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($item['total']) ?></td>
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
                <div class="no-print mb-3">
                    <h4 class="mt-3 mb-3">HSN/SAC Summary Report (<?= date('d-m-Y', strtotime($from_date)) ?> to <?= date('d-m-Y', strtotime($to_date)) ?>)</h4>
                    <button type="button" class="btn btn-sm btn-primary" onclick="printReport(true)">Print HSN/SAC Summary</button>
                </div>
                <div class="table-responsive print-section">
                    <table class="table table-bordered table-striped" id="hsn-summary-table">
                        <thead>
                            <tr>
                                <th>HSN/SAC</th>
                                <th>Rate</th>
                                <th>QTY</th>
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
                                SUM(id.qty) as total_qty,
                                CAST(TRIM(REPLACE(REPLACE(SUM(id.amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_amount,
                                CAST(TRIM(REPLACE(REPLACE(SUM(id.cgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_cgst,
                                CAST(TRIM(REPLACE(REPLACE(SUM(id.sgst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_sgst,
                                CAST(TRIM(REPLACE(REPLACE(SUM(id.igst_amount), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as total_igst,
                                CAST(TRIM(REPLACE(REPLACE(SUM(id.total), '262145 ', ''), '262145', '')) AS DECIMAL(10,2)) as grand_total
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
                            $total_qty = 0;
                            
                            while ($hsn = $hsn_result->fetch_assoc()):
                                // Strip the 262145 prefix from all fields in the HSN row
                                strip_prefix_from_array($hsn);
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
                                $total_qty += $hsn['total_qty'];
                            ?>
                                <?php if (!empty($hsn['hsn_sac'])): ?>
                                <tr>
                                    <td><?= htmlspecialchars($hsn['hsn_sac']) ?></td>
                                    <td><?= number_format($gst_rate, 2) ?>%</td>
                                    <td><?= display_number($hsn['total_qty']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_amount']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_cgst']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_sgst']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['total_igst']) ?></td>
                                    <td><?= CURRENCY_SYMBOL ?> <?= display_number($hsn['grand_total']) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                            <tr class="table-dark fw-bold">
                                <td colspan="2" class="text-end">Total:</td>
                                <td><?= display_number($total_qty) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= display_number($total_taxable) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= display_number($total_cgst) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= display_number($total_sgst) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= display_number($total_igst) ?></td>
                                <td><?= CURRENCY_SYMBOL ?> <?= display_number($total_amount) ?></td>
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
<style>
/* Direct CSS to eliminate duplicate table headers */
.dataTable thead tr:nth-child(n+2),
.dataTables_scrollHead {
    display: none !important;
}
</style>

<script>
$(document).ready(function() {
    // Remove any duplicate headers that might already exist in the DOM
    $('.dataTable thead tr:gt(0)').remove();
    
    // Initialize DataTables with configuration that prevents duplicate headers
    $('#gst-invoices-table').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "scrollX": false, /* Disable scrollX to prevent duplicate headers */
        "autoWidth": false,
        "columnDefs": [
            { "className": "text-nowrap", "targets": "_all" }
        ],
        "drawCallback": function() {
            // Remove any headers created during drawing
            $(this).find('thead tr:gt(0)').remove();
        }
    });
    
    $('#hsn-summary-table').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 50,
        "scrollX": false, /* Disable scrollX to prevent duplicate headers */
        "autoWidth": false,
        "columnDefs": [
            { "className": "text-nowrap", "targets": "_all" }
        ],
        "drawCallback": function() {
            // Remove any headers created during drawing
            $(this).find('thead tr:gt(0)').remove();
        }
    });
    
    // Print function for both report types
    $('.print-button').on('click', function() {
        // Add a title to the print based on which report is being printed
        let reportTitle = '';
        if ($(this).data('report-type') === 'detailed') {
            reportTitle = 'GST Detailed Report';
        } else {
            reportTitle = 'HSN/SAC Summary Report';
        }
        
        // Store the title in a data attribute to be used by CSS
        $('.print-section').attr('data-print-title', reportTitle);
        
        // Print the page
        window.print();
        
        return false;
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

<script>
// Fix for 262145 prefix - runs after page is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Function to recursively scan all text nodes and remove the prefix
    function removePrefixFromNode(node) {
        if (node.nodeType === 3) { // Text node
            const text = node.nodeValue;
            if (text && text.includes('262145')) {
                node.nodeValue = text.replace(/262145\s*/g, '');
            }
        } else if (node.nodeType === 1) { // Element node
            for (let i = 0; i < node.childNodes.length; i++) {
                removePrefixFromNode(node.childNodes[i]);
            }
        }
    }
    
    // Run the fix on the entire body
    removePrefixFromNode(document.body);
    
    // Also fix any content that might be added later by DataTables
    if (typeof $.fn.dataTable !== 'undefined') {
        $('.dataTable').on('draw.dt', function() {
            setTimeout(function() {
                removePrefixFromNode(document.body);
            }, 50);
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>
