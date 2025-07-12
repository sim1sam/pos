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

// Check if GST invoice table exists
echo "<h2>GST Invoice Table Structure</h2>";
$gst_invoice_structure_query = "DESCRIBE gst_invoices";
$gst_invoice_structure_result = $conn->query($gst_invoice_structure_query);

if ($gst_invoice_structure_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $gst_invoice_structure_result->fetch_assoc()) {
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
    echo "<p>Could not find GST invoice table or error: " . $conn->error . "</p>";
    
    // Check if GST data is stored in the main invoice table
    echo "<p>Checking if GST fields exist in the main invoice table...</p>";
    $check_gst_fields = $conn->query("SHOW COLUMNS FROM invoices LIKE '%gst%'");
    if ($check_gst_fields && $check_gst_fields->num_rows > 0) {
        echo "<p>Found GST-related fields in the main invoice table:</p>";
        echo "<ul>";
        while ($field = $check_gst_fields->fetch_assoc()) {
            echo "<li>" . $field['Field'] . "</li>";
        }
        echo "</ul>";
    }
    
    // Check for HSN/SAC fields
    $check_hsn_fields = $conn->query("SHOW COLUMNS FROM invoices LIKE '%hsn%'");
    if ($check_hsn_fields && $check_hsn_fields->num_rows > 0) {
        echo "<p>Found HSN-related fields in the main invoice table:</p>";
        echo "<ul>";
        while ($field = $check_hsn_fields->fetch_assoc()) {
            echo "<li>" . $field['Field'] . "</li>";
        }
        echo "</ul>";
    }
}

// Check invoice items table structure
echo "<h2>Invoice Items Table Structure</h2>";
$invoice_items_structure_query = "DESCRIBE invoice_items";
$invoice_items_structure_result = $conn->query($invoice_items_structure_query);

if ($invoice_items_structure_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $invoice_items_structure_result->fetch_assoc()) {
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
    echo "<p>Error fetching invoice items structure: " . $conn->error . "</p>";
}

// Check HSN/SAC configuration table if it exists
echo "<h2>HSN/SAC Configuration</h2>";
$hsn_structure_query = "DESCRIBE hsn_codes";
$hsn_structure_result = $conn->query($hsn_structure_query);

if ($hsn_structure_result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($field = $hsn_structure_result->fetch_assoc()) {
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
    echo "<p>Could not find HSN codes table or error: " . $conn->error . "</p>";
    
    // Try alternative table names
    $alt_tables = ["hsn_sac", "hsn_config", "gst_hsn_codes"];
    foreach ($alt_tables as $table) {
        $alt_query = "DESCRIBE $table";
        $alt_result = $conn->query($alt_query);
        if ($alt_result) {
            echo "<p>Found alternative HSN table: $table</p>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($field = $alt_result->fetch_assoc()) {
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
            break;
        }
    }
}

// Check for GST invoice pages
echo "<h2>GST Invoice Pages</h2>";
$gst_pages = [
    "pages/gst_invoices.php",
    "pages/invoice_gst_create.php"
];

foreach ($gst_pages as $page) {
    if (file_exists($page)) {
        echo "<p>Found GST page: $page</p>";
    } else {
        echo "<p>GST page not found: $page</p>";
    }
}

// Sample GST invoice data if available
echo "<h2>Sample GST Invoice Data</h2>";
$sample_query = "SELECT * FROM invoices WHERE invoice_type = 'gst' OR is_gst = 1 LIMIT 1";
$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    $invoice = $sample_result->fetch_assoc();
    echo "<pre>";
    print_r($invoice);
    echo "</pre>";
    
    // Check for related invoice items
    if (isset($invoice['id'])) {
        echo "<h3>Related Invoice Items</h3>";
        $items_query = "SELECT * FROM invoice_items WHERE invoice_id = " . $invoice['id'];
        $items_result = $conn->query($items_query);
        
        if ($items_result && $items_result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            $first_row = true;
            
            while ($item = $items_result->fetch_assoc()) {
                if ($first_row) {
                    echo "<tr>";
                    foreach (array_keys($item) as $key) {
                        echo "<th>" . $key . "</th>";
                    }
                    echo "</tr>";
                    $first_row = false;
                }
                
                echo "<tr>";
                foreach ($item as $value) {
                    echo "<td>" . $value . "</td>";
                }
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No invoice items found for this invoice.</p>";
        }
    }
} else {
    // Try alternative query
    $alt_query = "SELECT * FROM invoices WHERE gst_rate > 0 OR sgst_amount > 0 OR cgst_amount > 0 OR igst_amount > 0 LIMIT 1";
    $alt_result = $conn->query($alt_query);
    
    if ($alt_result && $alt_result->num_rows > 0) {
        $invoice = $alt_result->fetch_assoc();
        echo "<pre>";
        print_r($invoice);
        echo "</pre>";
    } else {
        echo "<p>No GST invoice data found.</p>";
    }
}
?>
