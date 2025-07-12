<?php require_once '../config.php'; ?>
<?php require_once '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .card-box {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }
        @media (max-width: 576px) {
            h5.mb-1 {
                font-size: 1rem;
            }
            p.text-muted {
                font-size: 0.85rem;
            }
            .card-icon {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<div class="container py-5">
    <h2 class="mb-4 text-center text-md-start">Invoice Center</h2>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
        <!-- All Invoices -->
        <div class="col">
            <a href="invoices.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-primary">📋</div>
                    <h5 class="mb-1">All Invoices</h5>
                    <p class="text-muted mb-0">Browse and manage all invoices</p>
                </div>
            </a>
        </div>

        <!-- GST Invoices -->
        <div class="col">
            <a href="gst_invoices.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-info">📄</div>
                    <h5 class="mb-1">GST Invoices</h5>
                    <p class="text-muted mb-0">View GST-compliant invoices</p>
                </div>
            </a>
        </div>
        
        <!-- GST Report -->
        <div class="col">
            <a href="gst_report.php" class="text-decoration-none text-dark">
                <div class="card card-box p-4">
                    <div class="card-icon text-success">📊</div>
                    <h5 class="mb-1">GST Report</h5>
                    <p class="text-muted mb-0">View detailed GST reports and analytics</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceDetailsModalLabel">GST Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading invoice details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables and other required scripts -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('.table').DataTable({
            "responsive": true,
            "pageLength": 10,
            "ordering": true,
            "info": true,
            "lengthChange": true,
            "searching": true
        });
        
        // Handle view details button click
        $('.view-details').on('click', function() {
            const invoiceId = $(this).data('id');
            $('#invoiceDetailsContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p>Loading invoice details...</p></div>');
            
            // Load invoice details via AJAX
            $.ajax({
                url: 'gst_invoice_details.php?id=' + invoiceId,
                type: 'GET',
                success: function(response) {
                    $('#invoiceDetailsContent').html(response);
                },
                error: function() {
                    $('#invoiceDetailsContent').html('<div class="alert alert-danger">Error loading invoice details. Please try again.</div>');
                }
            });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
