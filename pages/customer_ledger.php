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
    // Get customer details
    $customer_stmt = $conn->prepare("SELECT CONCAT(COALESCE(prefix, ''), ' ', name) AS display_name FROM customers WHERE id = ?");
    $customer_stmt->bind_param("i", $filter_customer);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    if ($customer_data = $customer_result->fetch_assoc()) {
        $customer_name = $customer_data['display_name'];
    }
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
        @media print {
            /* Hide everything by default */
            #layoutSidenav_nav, #layoutSidenav_content > nav, .card-header, .no-print, 
            .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
                display: none !important;
            }
            
            .container-fluid, .card, .card-body, .table {
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                width: 100% !important;
            }
            
            .table {
                font-size: 11px !important;
            }
            
            @page {
                size: landscape;
                margin: 1cm;
            }
            
            body {
                padding: 15px !important;
            }
            
            h1 {
                font-size: 18px !important;
                margin-bottom: 10px !important;
            }
            
            /* Add customer name and date range to top of printed page */
            .print-header {
                display: block !important;
                font-size: 14px;
                margin-bottom: 10px;
            }
            
            .print-header h2 {
                font-size: 16px !important;
                margin: 0 !important;
            }
        }
        
        .print-header {
            display: none;
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
        <h2>Customer Ledger: <?= htmlspecialchars($customer_name) ?></h2>
        <p>Period: <?= date('d M Y', strtotime($from_date)) ?> to <?= date('d M Y', strtotime($to_date)) ?></p>
    </div>
    
    <?php if ($filter_customer): ?>
    <!-- Ledger Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-1"></i>
                <?php 
                $filter_text = '';
                if ($filter_gst === 'with_gst') {
                    $filter_text = '(GST Invoices)';
                } else if ($filter_gst === 'without_gst') {
                    $filter_text = '(Non-GST Invoices)';
                }
                ?>
                Ledger for <?= htmlspecialchars($customer_name) ?> <?= $filter_text ?>
            </div>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-sm btn-primary">🖨️ Print Ledger</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Particular</th>
                        <th class="text-end">Debit (<?= CURRENCY_SYMBOL ?>)</th>
                        <th class="text-end">Credit (<?= CURRENCY_SYMBOL ?>)</th>
                        <th class="text-end">Balance (<?= CURRENCY_SYMBOL ?>)</th>
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
        </div>
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
