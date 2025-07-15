<?php
require_once '../config.php';
require_once '../includes/header.php';

// Default date range (current month)
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

// Get filter parameters
$from_date = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : $first_day_of_month;
$to_date = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : $today;
$filter_customer = isset($_GET['customer_id']) && !empty($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$filter_gst = isset($_GET['gst_filter']) ? $_GET['gst_filter'] : 'all'; // Options: all, with_gst, without_gst

// Get all customers for the filter dropdown
$customers = $conn->query("SELECT id, name, CONCAT(COALESCE(prefix, ''), ' ', name) AS display_name FROM customers ORDER BY name");

// Fetch company profile for print header/footer
$company = $conn->query("SELECT * FROM company_profile LIMIT 1")->fetch_assoc();

// Function to clean numeric values (similar to gst_report.php)
function clean_number($value) {
    if (is_null($value) || $value === '') {
        return 0;
    }
    // Check if the value contains the problematic prefix with space
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

// Prepare ledger data
$ledger_entries = [];
$total_debit = 0;
$total_credit = 0;
$opening_balance = 0;
$customer_name = "";

if ($filter_customer) {
    // Get the selected customer's details
    $customer_stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $customer_stmt->bind_param('i', $filter_customer);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    $customer = $customer_result->fetch_assoc();
    $customer_name = $customer ? $customer['name'] : 'Unknown';
    $customer_stmt->close();
    
    // Calculate opening balance (all transactions before from_date)
    // 1. Sum of invoices (debits) before from_date
    $opening_debit_query = "
        SELECT COALESCE(SUM(total_amount), 0) as total_debit 
        FROM invoices 
        WHERE customer_id = ? AND invoice_date < ?";
        
    // Apply GST filter to opening balance calculation too
    if ($filter_gst === 'with_gst') {
        $opening_debit_query .= " AND is_gst_invoice = 1";
    } else if ($filter_gst === 'without_gst') {
        $opening_debit_query .= " AND is_gst_invoice = 0";
    }
    
    $opening_debit_stmt = $conn->prepare($opening_debit_query);
    $opening_debit_stmt->bind_param("is", $filter_customer, $from_date);
    $opening_debit_stmt->execute();
    $opening_debit_result = $opening_debit_stmt->get_result();
    $opening_debit_data = $opening_debit_result->fetch_assoc();
    $opening_debit = clean_number($opening_debit_data['total_debit']);
    $opening_debit_stmt->close();
    
    // 2. Sum of payments (credits) before from_date
    $opening_credit_query = "
        SELECT COALESCE(SUM(amount), 0) as total_credit 
        FROM payments 
        WHERE customer_id = ? AND payment_date < ?";
    $opening_credit_stmt = $conn->prepare($opening_credit_query);
    $opening_credit_stmt->bind_param("is", $filter_customer, $from_date);
    $opening_credit_stmt->execute();
    $opening_credit_result = $opening_credit_stmt->get_result();
    $opening_credit_data = $opening_credit_result->fetch_assoc();
    $opening_credit = clean_number($opening_credit_data['total_credit']);
    $opening_credit_stmt->close();
    
    // Calculate opening balance (debit - credit)
    $opening_balance = $opening_debit - $opening_credit;

    // Get invoice entries (debits) within date range
    $invoice_query = "
        SELECT 
            id,
            invoice_no,
            invoice_date as transaction_date,
            total_amount as amount,
            CASE
                WHEN is_gst_invoice = 1 THEN 'GST Invoice'
                ELSE 'Non-GST Invoice'
            END as particular,
            'debit' as entry_type,
            is_gst_invoice
        FROM 
            invoices
        WHERE 
            customer_id = ?
            AND invoice_date BETWEEN ? AND ?";
            
    // Add GST filter condition
    if ($filter_gst === 'with_gst') {
        $invoice_query .= " AND is_gst_invoice = 1";
    } else if ($filter_gst === 'without_gst') {
        $invoice_query .= " AND is_gst_invoice = 0";
    }
    
    $invoice_query .= " ORDER BY transaction_date, id";
    
    $invoice_stmt = $conn->prepare($invoice_query);
    $invoice_stmt->bind_param("iss", $filter_customer, $from_date, $to_date);
    $invoice_stmt->execute();
    $invoice_result = $invoice_stmt->get_result();
    
    while ($invoice = $invoice_result->fetch_assoc()) {
        $debit_amount = clean_number($invoice['amount']);
        $total_debit += $debit_amount;
        
        $ledger_entries[] = [
            'date' => $invoice['transaction_date'],
            'particular' => $invoice['particular'] . ' #' . $invoice['invoice_no'],
            'debit' => $debit_amount,
            'credit' => 0,
            'entry_type' => 'debit'
        ];
    }
    $invoice_stmt->close();
    
    // Get payment entries (credits) within date range
    $payment_query = "
        SELECT 
            p.id,
            p.payment_date as transaction_date,
            p.amount as amount,
            CONCAT('Payment (', pm.mode_name, ')') as particular,
            'credit' as entry_type,
            p.note
        FROM 
            payments p
        LEFT JOIN 
            payment_modes pm ON p.payment_mode_id = pm.id
        WHERE 
            p.customer_id = ?
            AND p.payment_date BETWEEN ? AND ?
        ORDER BY 
            transaction_date, p.id";
    
    $payment_stmt = $conn->prepare($payment_query);
    $payment_stmt->bind_param("iss", $filter_customer, $from_date, $to_date);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    while ($payment = $payment_result->fetch_assoc()) {
        $credit_amount = clean_number($payment['amount']);
        $total_credit += $credit_amount;
        
        $note = !empty($payment['note']) ? " - " . $payment['note'] : "";
        $ledger_entries[] = [
            'date' => $payment['transaction_date'],
            'particular' => $payment['particular'] . $note,
            'debit' => 0,
            'credit' => $credit_amount,
            'entry_type' => 'credit'
        ];
    }
    $payment_stmt->close();
    
    // Sort all entries by date
    usort($ledger_entries, function($a, $b) {
        $date_compare = strtotime($a['date']) - strtotime($b['date']);
        if ($date_compare == 0) {
            // If same date, show debits before credits
            return ($a['entry_type'] == 'debit') ? -1 : 1;
        }
        return $date_compare;
    });
}

// Calculate closing balance
$closing_balance = $opening_balance + $total_debit - $total_credit;
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Customer Ledger</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Customer Ledger</li>
    </ol>
    
    <!-- Print-specific styles -->
    <style>
        body {
            font-family: 'Trebuchet MS', sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
        }
        
        .ledger-container {
            width: 21cm;
            min-height: 29.7cm;
            margin: auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        
        /* Table styling with page break control */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            page-break-inside: auto;
        }
        
        .table thead {
            display: table-header-group;
        }
        
        .table tbody {
            page-break-inside: avoid;
        }
        
        .table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        .text-end { text-align: right; }
        
        hr.divider {
            border: none;
            border-top: 2px solid #000;
            margin: 15px 0;
        }
        
        /* Page break control */
        .page-break {
            page-break-after: always;
        }
        
        /* Avoid breaking these elements across pages */
        .no-break {
            page-break-inside: avoid;
        }
        
        .print-header {
            display: none;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            
            .ledger-container, .ledger-container * {
                visibility: visible;
            }
            
            .ledger-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 21cm;
                height: auto; /* Allow height to adjust based on content */
                margin: 0;
                padding: 1cm;
                box-sizing: border-box;
                border: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Ensure proper page breaks */
            .table { page-break-inside: auto; }
            .table thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            
            /* Print header styling */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            
            /* A4 page size */
            @page {
                size: A4 portrait;
                margin: 1.5cm 0.5cm 0.5cm 0.5cm;
            }
        }
    </style>

    <!-- Filter Form -->
    <form method="get" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select" required>
                <option value="">Select Customer</option>
                <?php while ($cust = $customers->fetch_assoc()): ?>
                    <option value="<?= $cust['id'] ?>" <?= ($cust['id'] == $filter_customer ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cust['display_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">GST Filter</label>
            <select name="gst_filter" class="form-select">
                <option value="all" <?= $filter_gst == 'all' ? 'selected' : '' ?>>All Invoices</option>
                <option value="with_gst" <?= $filter_gst == 'with_gst' ? 'selected' : '' ?>>GST Invoices</option>
                <option value="without_gst" <?= $filter_gst == 'without_gst' ? 'selected' : '' ?>>Non-GST Invoices</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Search</button>
            <a href="customer_ledger.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <!-- Print header (hidden until print) -->
    <div class="print-header">
    </div>
    
    <!-- Print Button outside container -->
    <div class="text-center no-print" style="margin: 15px auto;">
        <button onclick="window.print()" class="btn btn-sm btn-danger">🖨️ Print Ledger</button>
    </div>
    
    <?php if ($filter_customer): ?>
    <!-- Define filter text based on GST filter -->
    <?php
    $filter_text = '';
    if ($filter_gst === 'with_gst') {
        $filter_text = '(GST Invoices)';
    } else if ($filter_gst === 'without_gst') {
        $filter_text = '(Non-GST Invoices)';
    }
    ?>
    
    <!-- Ledger Container for Print -->
    <div class="ledger-container">
        <!-- Print header with company logo and info -->
        <div style="margin-bottom: 20px;">
            <table width="100%">
                <tr>
                    <!-- Left: Logo -->
                    <td width="20%" style="text-align: center; vertical-align: middle;">
                        <?php if (!empty($company['logo'])): ?>
                            <img src="../uploads/<?= $company['logo'] ?>" style="max-height: 80px; max-width: 100%;">
                        <?php endif; ?>
                    </td>
                    <!-- Right: Company Info -->
                    <td width="80%" style="text-align: center; vertical-align: middle;">
                        <h2 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">
                            <?= $company['name'] ?? 'Company Name' ?>
                        </h2>
                        <div style="font-size: 14px; margin-bottom: 5px;">
                            <?= $company['address'] ?? 'Company Address' ?>
                        </div>
                        <div style="font-size: 14px; margin-bottom: 5px;">
                            <?= $company['phone'] ?? 'Phone' ?> | <?= $company['email'] ?? 'Email' ?>
                        </div>
                        <div style="font-size: 14px;">
                            GSTIN: <?= $company['gstin'] ?? 'N/A' ?>
                        </div>
                    </td>
                </tr>
            </table>
            <hr class="divider" style="margin-bottom: 10px;">
            <h3 style="font-size: 16px; font-weight: bold; margin-bottom: 0; text-align: center;">
                Customer Ledger Statement <?= $filter_text ?>
            </h3>
        </div>
        
        <!-- Customer Details -->
        <div style="margin-bottom: 15px;">
            <table width="100%">
                <tr>
                    <td width="50%" style="vertical-align: top;">
                        <div style="font-weight: bold;">Customer Details:</div>
                        <div><strong><?= htmlspecialchars($customer_name) ?></strong></div>
                        <div>GSTIN: <?= htmlspecialchars($customer['gstin'] ?? 'N/A') ?></div>
                        <div>Phone: <?= htmlspecialchars($customer['mobile'] ?? 'N/A') ?></div>
                    </td>
                    <td width="50%" style="vertical-align: top; text-align: right;">
                        <div>Report Date: <?= date('d-M-Y') ?></div>
                        <div>Period: <?= date('d-M-Y', strtotime($from_date)) ?> to <?= date('d-M-Y', strtotime($to_date)) ?></div>
                    </td>
                </tr>
            </table>
        </div>
        
        <hr class="divider" style="margin-bottom: 10px;">
        
        <!-- Ledger Table -->
        <?php if (count($ledger_entries) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered" id="ledgerTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Particulars</th>
                        <th class="text-end">Debit (₹)</th>
                        <th class="text-end">Credit (₹)</th>
                        <th class="text-end">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="table-light">
                        <td><?= date('d M Y', strtotime($from_date)) ?></td>
                        <td><strong>Opening Balance</strong></td>
                        <td class="text-end"><?= $opening_balance > 0 ? number_format($opening_balance, 2) : '' ?></td>
                        <td class="text-end"><?= $opening_balance < 0 ? number_format(abs($opening_balance), 2) : '' ?></td>
                        <td class="text-end"><?= $opening_balance >= 0 ? 
                            number_format($opening_balance, 2) . ' Dr' : 
                            number_format(abs($opening_balance), 2) . ' Cr' ?></td>
                    </tr>
                    
                    <?php 
                    $running_balance = $opening_balance;
                    foreach ($ledger_entries as $entry): 
                        if ($entry['entry_type'] == 'debit') {
                            $running_balance += $entry['debit'];
                        } else {
                            $running_balance -= $entry['credit'];
                        }
                    ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($entry['date'])) ?></td>
                        <td><?= htmlspecialchars($entry['particular']) ?></td>
                        <td class="text-end"><?= $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '' ?></td>
                        <td class="text-end"><?= $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '' ?></td>
                        <td class="text-end"><?= $running_balance >= 0 ? 
                            number_format($running_balance, 2) . ' Dr' : 
                            number_format(abs($running_balance), 2) . ' Cr' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Closing Balance Row -->
                    <tr class="table-light">
                        <td><?= date('d M Y', strtotime($to_date)) ?></td>
                        <td><strong>Closing Balance</strong></td>
                        <td class="text-end"><strong><?= number_format($total_debit, 2) ?></strong></td>
                        <td class="text-end"><strong><?= number_format($total_credit, 2) ?></strong></td>
                        <td class="text-end"><strong><?= $closing_balance >= 0 ? 
                            number_format($closing_balance, 2) . ' Dr' : 
                            number_format(abs($closing_balance), 2) . ' Cr' ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Additional notes and footer information -->
            <div style="margin-top: 30px;">
                <table width="100%" style="margin-top: 20px;">
                    <tr>
                        <!-- Company Banking Details -->
                        <td width="35%" style="text-align: left; vertical-align: top; font-size: 11px;">
                            <strong>Payment Account Details:</strong><br>
                            A/C Name: <?= $company['acc_name'] ?? '---' ?><br>
                            A/C Number: <?= $company['acc_number'] ?? '---' ?><br>
                            IFS Code: <?= $company['ifsc_code'] ?? '---' ?><br>
                            Branch: <?= $company['branch'] ?? '---' ?><br>
                            Bank Name: <?= $company['bank_name'] ?? '---' ?><br>
                            Company PAN#: <?= $company['pan_number'] ?? '---' ?>
                        </td>
                
                        <!-- Right: Declaration + Sign -->
                        <td width="65%" style="text-align: right; vertical-align: top; font-size: 11px;">
                            <p style="margin-top: 5px; font-weight: bold;">For <?= $company['name'] ?? 'Company Name' ?><br><br><br>Authorized Signatory</p>
                        </td>
                    </tr>
                </table>
                <hr class="divider">
                <p class="text-center" style="font-size:9px; color:#555; margin-top:5px">
                    (This is a system generated ledger statement)
                </p>
            </div>
        <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> Please select a customer to view their ledger.
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Add DataTable for better sorting and searching
    $('.table').DataTable({
        "ordering": false,
        "paging": false,
        "info": false,
        "searching": false,
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
