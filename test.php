<?php
// Simple test file to check if PHP is working
require_once 'config.php';

echo "PHP is working!";
echo "<br>Current time: " . date('Y-m-d H:i:s');
echo "<br>Database name: " . DB_DATABASE;

// Test database connection
try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($conn->connect_error) {
        echo "<br><strong style='color:red'>Database connection failed:</strong> " . $conn->connect_error;
    } else {
        echo "<br><strong style='color:green'>Database connection successful!</strong>";
        
        // Check if users table exists
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows > 0) {
            echo "<br>Users table exists";
        } else {
            echo "<br><strong style='color:red'>Users table does not exist!</strong>";
        }
    }
} catch (Exception $e) {
    echo "<br><strong style='color:red'>Error:</strong> " . $e->getMessage();
}
?>
