<?php
require_once '../config.php';

// Use the invoice_id passed from invoice_pdf.php or invoice_view.php
// If no invoice_id is provided, fall back to the most recent invoice
if (!isset($invoice_id)) {
    $invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
}

// If we still don't have an invoice_id, get the latest invoice
if (!$invoice_id) {
    $invoice = $conn->query("SELECT * FROM invoices ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $invoice_id = $invoice['id'];
} else {
    // Get the specific invoice by ID
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        die("Invoice #$invoice_id not found");
    }
}

$customer = $conn->query("SELECT * FROM customers WHERE id = {$invoice['customer_id']}")->fetch_assoc();
$items = $conn->query("SELECT * FROM invoice_details WHERE invoice_id = {$invoice_id}");
$company = $conn->query("SELECT * FROM company_profile LIMIT 1")->fetch_assoc();
$gst_type = strtolower($invoice['gst_type']);

$hsn_summary = [];
foreach ($conn->query("SELECT hsn_sac, SUM(amount) AS taxable_value, SUM(sgst_amount) AS sgst, SUM(cgst_amount) AS cgst, SUM(igst_amount) AS igst FROM invoice_details WHERE invoice_id = {$invoice_id} GROUP BY hsn_sac") as $row) {
    $hsn_summary[] = $row;
}

function format_date($date_str) {
    return date('d-M-Y', strtotime($date_str));
}
?>

<style>
/* Basic styling */
body {
    font-family: 'Trebuchet MS', sans-serif;
    font-size: 11px;
    color: #000;
    background: #fff;
    margin: 0;
    padding: 0;
}

/* Container styling with proper page breaks */
.invoice-container {
    width: 100%;
    padding: 10px;
    box-sizing: border-box;
}

/* Table styling */
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

.table th, .table td {
    border: 1px solid #999;
    padding: 5px 8px;
    text-align: left;
}

/* Utility classes */
.text-end { text-align: right; }

/* Page break control */
.page-break {
    page-break-after: always;
}

/* Avoid breaking these elements across pages */
.no-break {
    page-break-inside: avoid;
}

hr.divider {
    border: none;
    border-top: 2px solid #000;
    margin: 15px 0;
}

/* Footer styling */
.invoice-footer {
    position: running(footer);
    text-align: center;
    font-size: 8px;
    color: #555;
}

@page {
    margin: 15mm 10mm 15mm 10mm;
    @bottom-center {
        content: element(footer);
    }
}
</style>

<div class="invoice-container">
    <table width="100%">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <img src="../uploads/<?= $company['logo'] ?>" alt="logo" style="height: 50px;">
            </td>
            <td style="width: 50%; text-align: right; vertical-align: top;">
                <div style="font-size:12px; font-weight: bold;">Original for Recipient</div>
                <div style="font-size:13px; font-weight: bold;">TAX INVOICE# <?= $invoice['invoice_no'] ?></div>
                <div style="font-size:13px;">Billing Date: <?= format_date($invoice['invoice_date']) ?></div>
            </td>
        </tr>
    </table>
    <hr class="divider">

    <!-- Company & Customer Details -->
    <table width="100%" style="margin-top: 20px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold; text-decoration: underline;">Billed By:</div>
                <div style="font-weight: bold; text-transform: uppercase;"><?= $company['name'] ?></div>
                <div><?= $company['address'] ?></div>
                <div><?= $company['city'] ?> - <?= $company['pin'] ?>, <?= $company['state'] ?></div>
                <div>GSTIN# <?= $company['gstin'] ?></div>
                <div>Email: <?= $company['email'] ?></div>
                <div>Mobile#: <?= $company['mobile'] ?></div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold; text-decoration: underline;">Billed To:</div>
                <div style="font-weight: bold; text-transform: uppercase;">
                    <?= $customer['prefix'] ?> <?= $customer['name'] ?>
                </div>
                <div><strong>Address:</strong> <?= $customer['address'] ?></div>
                <div><?= $customer['city'] ?> - <?= $customer['pin'] ?>, <?= $customer['state'] ?></div>
                <div>GSTIN# <?= $customer['gstin'] ?></div>
                <div>Email: <?= $customer['email'] ?></div>
                <div>Mobile#: <?= $customer['mobile'] ?></div>
            </td>
        </tr>
    </table>
    <hr class="divider">

    <!-- Invoice Table -->
    <table class="table">
        <thead>
            <tr>
                <th style="width: 4%;">SL#</th>
                <th style="width: 43%;">Description of Goods</th>
                <th style="width: 8%;">HSN/SAC</th>
                <th style="width: 12%;">Rate (Rs.)</th>
                <th style="width: 5%;">Qty.</th>
                <th style="width: 5%;">Unit</th>
                <th style="width: 8%;">Disc.</th>
                <th style="width: 18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; $total = 0; $total_qty = 0;
            while ($item = $items->fetch_assoc()):
                $total += $item['amount'];
                $total_qty += $item['qty']; ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= $item['description'] ?></td>
                <td><?= $item['hsn_sac'] ?></td>
                <td>₹<?= number_format($item['rate'], 2) ?></td>
                <td><?= $item['qty'] ?></td>
                <td><?= $item['unit'] ?></td>
                <td><?= number_format($item['discount'], 2) ?></td>
                <td>₹<?= number_format($item['amount'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-end"><strong>Subtotal</strong></td>
                <td>₹<?= number_format($invoice['subtotal'], 0) ?></td>
            </tr>
            <?php if ($gst_type === 'same'): ?>
                <tr>
                    <td colspan="7" class="text-end"><strong>SGST</strong></td>
                    <td>₹<?= number_format($invoice['total_tax'] / 2, 0) ?></td>
                </tr>
                <tr>
                    <td colspan="7" class="text-end"><strong>CGST</strong></td>
                    <td>₹<?= number_format($invoice['total_tax'] / 2, 0) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-end"><strong>IGST</strong></td>
                    <td>₹<?= number_format($invoice['total_tax'], 0) ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td colspan="7" class="text-end fw-bold">Total Amount</td>
                <td class="fw-bold">₹<?= number_format($invoice['total_amount'], 0) ?></td>
            </tr>
        </tfoot>
    </table>
    
    <table width="100%" style="margin-top: 5px;">
        <tr>
            <td style="font-weight: bold; font-size: 13px; text-align: left;">
                Amount in Words: INR <?= ucwords(convert_number_to_words(round($invoice['total_amount']))) ?> Only
            </td>
            <td style="font-size: 12px; font-weight: bold; text-align: right; background: #fff3cd; padding: 6px 10px; border-radius: 4px; border: 1px solid #ffeeba;">
                Please Pay by: <?= format_date($invoice['due_date']) ?>
            </td>
        </tr>
    </table>

    <!-- Tax Calculation Table -->
    <br>
    <?php
    $sum_taxable = 0;
    $sum_sgst = 0;
    $sum_cgst = 0;
    $sum_igst = 0;
    foreach ($hsn_summary as $r) {
        $sum_taxable += $r['taxable_value'];
        $sum_sgst += $r['sgst'];
        $sum_cgst += $r['cgst'];
        $sum_igst += $r['igst'];
    }
    ?>
    <table class="table">
        <thead>
            <tr>
                <th>HSN / SAC</th>
                <th>Taxable Value</th>
                <th>Rate (%)</th>
                <th>SGST</th>
                <th>CGST</th>
                <th>IGST</th>
                <th>Total Tax Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hsn_summary as $row): 
                // Prevent division by zero by checking if taxable_value is zero
                if ($row['taxable_value'] > 0) {
                    $gst_rate = $gst_type === 'same'
                        ? ($row['sgst'] + $row['cgst']) / $row['taxable_value'] * 100
                        : ($row['igst'] / $row['taxable_value']) * 100;
                } else {
                    $gst_rate = 0; // Default to 0% if taxable value is 0
                }
            ?>
            <tr>
                <td><?= $row['hsn_sac'] ?></td>
                <td>₹<?= number_format($row['taxable_value'], 2) ?></td>
                <td><?= number_format($gst_rate, 2) ?>%</td>
                <td>₹<?= number_format($row['sgst'], 2) ?></td>
                <td>₹<?= number_format($row['cgst'], 2) ?></td>
                <td>₹<?= number_format($row['igst'], 2) ?></td>
                <td>₹<?= number_format($row['sgst'] + $row['cgst'] + $row['igst'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr style="font-weight: bold; background: #f8f9fa;">
                <td>Total</td>
                <td>₹<?= number_format($sum_taxable, 2) ?></td>
                <td>—</td>
                <td>₹<?= number_format($sum_sgst, 2) ?></td>
                <td>₹<?= number_format($sum_cgst, 2) ?></td>
                <td>₹<?= number_format($sum_igst, 2) ?></td>
                <td>₹<?= number_format($sum_sgst + $sum_cgst + $sum_igst, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <p><strong>Tax Amount in Words:</strong> INR <?= ucwords(convert_number_to_words(round($invoice['total_tax']))) ?> Only</p>

    <!-- Footer Part -->
    <div class="no-break">
        <table width="100%" style="margin-top: 20px;">
            <tr>
                <!-- Left: Bank Account Details -->
                <td width="35%" style="vertical-align: top;">
                    <strong>Payment Account Details:</strong><br>
                    A/C Name: <?= $company['acc_name'] ?><br>
                    A/C Number: <?= $company['acc_number'] ?><br>
                    IFS Code: <?= $company['ifsc_code'] ?><br>
                    Branch: <?= $company['branch'] ?><br>
                    Bank Name: <?= $company['bank_name'] ?><br>
                    Company PAN#: <?= $company['pan_number'] ?>
                </td>
        
                <!-- Center: QR Code -->
                <td width="15%" style="text-align: left; vertical-align: top;">
                    <img src="../uploads/<?= $company['qr_code'] ?>" style="height: 100px;"><br>
                </td>
        
                <!-- Right: Declaration + Sign -->
                <td width="50%" style="text-align: right; vertical-align: top;">
                    <p><strong>Declaration:</strong><br><?= $company['declaration'] ?></p>
                    <p style="margin-top: 5px; font-weight: bold;">For <?= $company['name'] ?><br><br><br>Authorized Signatory</p>
                </td>
            </tr>
        </table>
        <hr class="divider">
        <p class="text-center mt-3" style="font-size:8px; color:#555; margin-top:5px">
            (This is a system generated invoice, and doesn't require any signature.)
        </p>
    </div>
</div>

<?php
function format_date($date) {
    if (!$date) return 'N/A';
    return date('d-m-Y', strtotime($date));
}

function convert_number_to_words($number) {
    $hyphen = '-';
    $conjunction = ' and ';
    $separator = ' ';
    $negative = 'negative ';
    $decimal = ' point ';
    $dictionary = [
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
        20 => 'twenty',
        30 => 'thirty',
        40 => 'forty',
        50 => 'fifty',
        60 => 'sixty',
        70 => 'seventy',
        80 => 'eighty',
        90 => 'ninety',
        100 => 'hundred',
        1000 => 'thousand',
        100000 => 'lakh',
        10000000 => 'crore'
    ];

    if (!is_numeric($number)) {
        return false;
    }

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        return false;
    }

    if ($number < 0) {
        return $negative . convert_number_to_words(abs($number));
    }

    $string = $fraction = null;

    if (strpos($number, '.') !== false) {
        [$number, $fraction] = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds = (int) ($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . convert_number_to_words($remainder);
            }
            break;
        case $number < 100000:
            $thousands = (int) ($number / 1000);
            $remainder = $number % 1000;
            $string = convert_number_to_words($thousands) . ' ' . $dictionary[1000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder);
            }
            break;
        case $number < 10000000:
            $lakhs = (int) ($number / 100000);
            $remainder = $number % 100000;
            $string = convert_number_to_words($lakhs) . ' ' . $dictionary[100000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder);
            }
            break;
        default:
            $crores = (int) ($number / 10000000);
            $remainder = $number % 10000000;
            $string = convert_number_to_words($crores) . ' ' . $dictionary[10000000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction)) {
        $string .= $decimal;
        $words = [];
        foreach (str_split((string) $fraction) as $number) {
            $words[] = $dictionary[$number];
        }
        $string .= implode(' ', $words);
    }

    return $string;
}
