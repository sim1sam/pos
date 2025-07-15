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
            margin: 0;
            padding: 0;
        }
        
        .ledger-container {
            width: 100%;
            max-width: 21cm;
            margin: auto;
            padding: 10px;
            background: #fff;
            box-sizing: border-box;
        }
        
        .company-header {
            margin-bottom: 5px;
        }
        
        .customer-details {
            margin-bottom: 5px;
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
        
        .table tr {
            page-break-inside: avoid;
        }
        
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 3px;
        }
        
        .text-end { text-align: right; }
        
        hr.divider {
            border: none;
            border-top: 1px solid #000;
            margin: 5px 0;
        }
        
        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 5mm !important;
                padding: 0 !important;
            }
            
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
                width: 100%;
                height: auto;
                margin: 0;
                padding: 5mm;
                box-sizing: border-box;
                border: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page {
                page-break-after: always;
            }
            .no-break {
                page-break-inside: avoid;
            }
            .company-header {
                margin-bottom: 0;
            }
            .ledger-content {
                display: block;
            }
            tr {
                page-break-inside: avoid;
            }
            /* Repeat table headers on each page */
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
            table {
                width: 100%;
                page-break-inside: auto;
            }
            /* Add page number */
            @page {
                margin: 5mm;
                size: A4;
                counter-increment: page;
            }
            .page-number:after {
                content: counter(page);
            }
            /* Add table header to each page */
            thead tr {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            /* Fix for Firefox and Chrome differences */
            .table {
                border-collapse: collapse;
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
    
    <!-- Compact Print Layout -->
    <div class="ledger-container">
        <table width="100%" border="0" cellspacing="0" cellpadding="0" class="company-header">
            <tr>
                <!-- Left: Logo -->
                <td width="20%" style="text-align: center; vertical-align: middle;">
                    <?php if (!empty($company['logo'])): ?>
                        <img src="../uploads/<?= $company['logo'] ?>" style="max-height: 60px; max-width: 100%;">
                    <?php endif; ?>
                </td>
                <!-- Right: Company Info -->
                <td width="80%" style="text-align: center; vertical-align: middle;">
                    <h2 style="font-size: 16px; font-weight: bold; margin-bottom: 2px;">
                        <?= $company['name'] ?? 'Company Name' ?>
                    </h2>
                    <div style="font-size: 12px; margin-bottom: 2px;">
                        <?= $company['address'] ?? 'Company Address' ?>
                    </div>
                    <div style="font-size: 12px; margin-bottom: 2px;">
                        <?= $company['phone'] ?? 'Phone' ?> | <?= $company['email'] ?? 'Email' ?> | GSTIN: <?= $company['gstin'] ?? 'N/A' ?>
                    </div>
                </td>
            </tr>
        </table>
        <hr class="divider">
        <h3 style="font-size: 14px; font-weight: bold; margin: 5px 0; text-align: center;">
            Customer Ledger Statement <?= $filter_text ?>
        </h3>
        
        <!-- Customer Details - Compact -->
        <table width="100%" cellspacing="0" cellpadding="2" style="margin-bottom: 5px;">
            <tr>
                <td width="70%" style="vertical-align: top; font-size: 11px;">
                    <span style="font-weight: bold;">Customer:</span> <strong><?= htmlspecialchars($customer_name) ?></strong><br>
                    <span style="font-weight: bold;">GSTIN:</span> <?= htmlspecialchars($customer['gstin'] ?? 'N/A') ?> | 
                    <span style="font-weight: bold;">Phone:</span> <?= htmlspecialchars($customer['mobile'] ?? 'N/A') ?>
                </td>
                <td width="30%" style="vertical-align: top; text-align: right; font-size: 11px;">
                    <span style="font-weight: bold;">Report Date:</span> <?= date('d-M-Y') ?><br>
                    <span style="font-weight: bold;">Period:</span> <?= date('d-M-Y', strtotime($from_date)) ?> to <?= date('d-M-Y', strtotime($to_date)) ?>
                </td>
            </tr>
        </table>
            
            <hr style="border-top: 1px solid #ddd; margin: 2px 0;">
            
            <!-- Ledger Table with Multi-Page Support -->
            <?php if (count($ledger_entries) > 0): ?>
            <table class="table" cellspacing="0" cellpadding="3" style="margin-top: 2px; font-size: 10px; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="width: 15%; border-bottom: 1px solid #ddd;">Date</th>
                        <th style="width: 35%; border-bottom: 1px solid #ddd;">Particulars</th>
                        <th class="text-end" style="width: 15%; border-bottom: 1px solid #ddd;">Debit (₹)</th>
                        <th class="text-end" style="width: 15%; border-bottom: 1px solid #ddd;">Credit (₹)</th>
                        <th class="text-end" style="width: 20%; border-bottom: 1px solid #ddd;">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr style="background-color: #f8f8f8;">
                        <td><?= date('d-m-Y', strtotime($from_date)) ?></td>
                        <td><strong>Opening Balance</strong></td>
                        <td class="text-end"><?= $opening_balance > 0 ? number_format($opening_balance, 2) : '-' ?></td>
                        <td class="text-end"><?= $opening_balance < 0 ? number_format(abs($opening_balance), 2) : '-' ?></td>
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
                    <tr style="border-bottom: 1px solid #eee;">
                        <td><?= date('d-m-Y', strtotime($entry['date'])) ?></td>
                        <td><?= htmlspecialchars($entry['particular']) ?></td>
                        <td class="text-end"><?= $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-' ?></td>
                        <td class="text-end"><?= $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-' ?></td>
                        <td class="text-end"><?= $running_balance >= 0 ? 
                            number_format($running_balance, 2) . ' Dr' : 
                            number_format(abs($running_balance), 2) . ' Cr' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Closing Balance Row -->
                    <tr style="background-color: #f8f8f8; font-weight: bold;">
                        <td><?= date('d-m-Y', strtotime($to_date)) ?></td>
                        <td><strong>Closing Balance</strong></td>
                        <td class="text-end"><?= number_format($total_debit, 2) ?></td>
                        <td class="text-end"><?= number_format($total_credit, 2) ?></td>
                        <td class="text-end"><?= $closing_balance >= 0 ? 
                            number_format($closing_balance, 2) . ' Dr' : 
                            number_format(abs($closing_balance), 2) . ' Cr' ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Table Footer (will repeat on each page) -->
            <tfoot>
                <tr>
                    <td colspan="5">
                        <div style="height: 5px;"></div>
                    </td>
                </tr>
            </tfoot>
            
            <!-- Compact footer with banking details -->
            <div style="margin-top: 5px;">
                <hr style="border-top: 1px solid #000; margin: 5px 0;">
                <table width="100%" cellspacing="0" cellpadding="2" style="margin-top: 5px;">
                    <tr>
                        <!-- Banking Details - Left column -->
                        <td width="60%" style="text-align: left; vertical-align: top; font-size: 9px;">
                            <strong>Payment Account Details:</strong>
                            A/C Name: <?= $company['acc_name'] ?? '---' ?> | 
                            A/C #: <?= $company['acc_number'] ?? '---' ?> | 
                            IFSC: <?= $company['ifsc_code'] ?? '---' ?><br>
                            Branch: <?= $company['branch'] ?? '---' ?> | 
                            Bank: <?= $company['bank_name'] ?? '---' ?> | 
                            PAN#: <?= $company['pan_number'] ?? '---' ?>
                        </td>
                
                        <!-- Signature - Right column -->
                        <td width="40%" style="text-align: right; vertical-align: bottom; font-size: 9px;">
                            <p style="margin: 5px 0 20px 0;">For <?= $company['name'] ?? 'Company Name' ?><br>Authorized Signatory</p>
                        </td>
                    </tr>
                </table>
                <p class="text-center" style="font-size:8px; color:#555; margin-top:2px">
                    (This is a system generated ledger statement) <span class="page-number">Page </span>
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
