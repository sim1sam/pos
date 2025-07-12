<?php
// Include configuration file
require_once 'config.php';

echo "<h1>Invoice System Analysis</h1>";

// Check database tables
echo "<h2>Database Tables</h2>";
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);

if ($tables_result) {
    echo "<ul>";
    while ($table = $tables_result->fetch_array()) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Error fetching tables: " . $conn->error . "</p>";
}

// Check invoice table structure
echo "<h2>Invoice Table Structure</h2>";
$invoice_structure_query = "DESCRIBE invoices";
$invoice_structure_result = $conn->query($invoice_structure_query);

if ($invoice_structure_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $invoice_structure_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . $field['Default'] . "</td>";
        echo "<td>" . $field['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error fetching invoice structure: " . $conn->error . "</p>";
}

// Check invoice details table structure (for GST items)
echo "<h2>Invoice Details Table Structure</h2>";
$invoice_details_query = "DESCRIBE invoice_details";
$invoice_details_result = $conn->query($invoice_details_query);

if ($invoice_details_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $invoice_details_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . $field['Default'] . "</td>";
        echo "<td>" . $field['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error fetching invoice details structure: " . $conn->error . "</p>";
}

// Check GST configuration
echo "<h2>GST Configuration Table</h2>";
$gst_config_query = "DESCRIBE gst_config";
$gst_config_result = $conn->query($gst_config_query);

if ($gst_config_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $gst_config_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . $field['Default'] . "</td>";
        echo "<td>" . $field['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error fetching GST configuration structure: " . $conn->error . "</p>";
}

// Check for GST-related fields in invoice_details
echo "<h2>GST Fields in Invoice Details</h2>";
$check_gst_fields = $conn->query("SHOW COLUMNS FROM invoice_details");
if ($check_gst_fields) {
    echo "<p>Fields in invoice_details table:</p>";
    echo "<ul>";
    $gst_related_fields = [];
    while ($field = $check_gst_fields->fetch_assoc()) {
        $field_name = $field['Field'];
        if (strpos($field_name, 'gst') !== false || 
            strpos($field_name, 'tax') !== false || 
            strpos($field_name, 'hsn') !== false || 
            strpos($field_name, 'sac') !== false ||
            strpos($field_name, 'cgst') !== false ||
            strpos($field_name, 'sgst') !== false ||
            strpos($field_name, 'igst') !== false) {
            $gst_related_fields[] = $field_name;
            echo "<li><strong>" . $field_name . "</strong> - " . $field['Type'] . "</li>";
        } else {
            echo "<li>" . $field_name . " - " . $field['Type'] . "</li>";
        }
    }
    echo "</ul>";
    
    if (empty($gst_related_fields)) {
        echo "<p>No GST-related fields found in invoice_details table.</p>";
    }
} else {
    echo "<p>Error checking invoice_details fields: " . $conn->error . "</p>";
}

// Sample invoice data
echo "<h2>Sample Invoice Data</h2>";
$sample_query = "SELECT * FROM invoices WHERE is_gst_invoice = 1 LIMIT 1";
$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    $invoice = $sample_result->fetch_assoc();
    echo "<h3>GST Invoice</h3>";
    echo "<table border='1' cellpadding='5'>";
    foreach ($invoice as $key => $value) {
        echo "<tr>";
        echo "<td><strong>" . $key . "</strong></td>";
        echo "<td>" . $value . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for related invoice details
    if (isset($invoice['id'])) {
        echo "<h3>Related Invoice Details</h3>";
        $details_query = "SELECT * FROM invoice_details WHERE invoice_id = " . $invoice['id'];
        $details_result = $conn->query($details_query);
        
        if ($details_result && $details_result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            $first_row = true;
            
            while ($detail = $details_result->fetch_assoc()) {
                if ($first_row) {
                    echo "<tr>";
                    foreach (array_keys($detail) as $key) {
                        echo "<th>" . $key . "</th>";
                    }
                    echo "</tr>";
                    $first_row = false;
                }
                
                echo "<tr>";
                foreach ($detail as $value) {
                    echo "<td>" . $value . "</td>";
                }
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No invoice details found for this invoice.</p>";
        }
    }
} else {
    echo "<p>No GST invoice found.</p>";
    
    // Show a regular invoice as example
    $reg_query = "SELECT * FROM invoices LIMIT 1";
    $reg_result = $conn->query($reg_query);
    
    if ($reg_result && $reg_result->num_rows > 0) {
        $reg_invoice = $reg_result->fetch_assoc();
        echo "<h3>Regular Invoice (for reference)</h3>";
        echo "<table border='1' cellpadding='5'>";
        foreach ($reg_invoice as $key => $value) {
            echo "<tr>";
            echo "<td><strong>" . $key . "</strong></td>";
            echo "<td>" . $value . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check GST invoice pages
echo "<h2>GST Invoice Pages</h2>";
$gst_pages = [
    "pages/gst_invoices.php",
    "pages/invoice_gst_create.php"
];

foreach ($gst_pages as $page) {
    if (file_exists($page)) {
        echo "<p>Found GST page: $page</p>";
        
        // Show content of the GST invoice page
        echo "<h3>Content of " . basename($page) . "</h3>";
        echo "<pre style='background-color: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
        $content = htmlspecialchars(file_get_contents($page));
        echo $content;
        echo "</pre>";
    } else {
        echo "<p>GST page not found: $page</p>";
    }
}

// Check for date filtering functionality in GST invoices
echo "<h2>Date Filtering Functionality</h2>";
$date_filter_pages = [
    "pages/gst_invoices.php",
    "pages/invoices.php"
];

foreach ($date_filter_pages as $page) {
    if (file_exists($page)) {
        echo "<h3>Date Filtering in " . basename($page) . "</h3>";
        $content = file_get_contents($page);
        
        // Check for date filter forms or inputs
        if (strpos($content, 'date') !== false && (strpos($content, 'filter') !== false || strpos($content, 'search') !== false)) {
            echo "<p>Found date filtering functionality in $page</p>";
            
            // Extract date filter code snippet
            preg_match('/<form[^>]*>.*?<\/form>/s', $content, $form_matches);
            if (!empty($form_matches)) {
                echo "<pre style='background-color: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;'>";
                echo htmlspecialchars($form_matches[0]);
                echo "</pre>";
            }
        } else {
            echo "<p>No date filtering functionality found in $page</p>";
        }
    }
}
?>
