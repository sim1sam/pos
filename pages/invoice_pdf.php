<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../dompdf/autoload.inc.php';
require_once '../config.php';

use Dompdf\Dompdf;

// Get invoice ID from URL parameter
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$invoice_id) {
    die('Invoice ID is required');
}

function format_date($date_str) {
    return date('d-M-Y', strtotime($date_str));
}

// Pass invoice_id to the content file
ob_start();
include 'invoice_view_content.php';
$html = ob_get_clean();

// Configure DOMPDF with options for better multi-page handling
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Generate a meaningful filename with the invoice number
$invoice_number = $invoice['invoice_no'] ?? $invoice_id;
$filename = "Invoice-{$invoice_number}.pdf";

$dompdf->stream($filename, ["Attachment" => true]);
exit;
