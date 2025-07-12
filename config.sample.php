<?php

// config.sample.php - Sample configuration file
// Copy this file to config.php and update with your actual settings

// Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_DATABASE', 'your_database');

// App settings
define('APP_NAME', 'POS System');
define('APP_URL', 'http://your-domain.com');
define('APP_ENV', 'production'); // Change to 'local' for development
define('APP_TIMEZONE', 'Asia/Kolkata');
define('APP_LOCALE', 'en');

// BASE URL CONSTANT
define('BASE_URL', ''); // For linking pages, assets, etc.

// MAIL SETTINGS
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your_email@example.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_FROM_ADDRESS', 'your_email@example.com');
define('MAIL_FROM_NAME', 'Your Name');

// Error Reporting
define('ERROR_LOGGING', true);
define('ERROR_LOG_PATH', 'logs/error.log');

// Define Custom Constants
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('CURRENCY_SYMBOL', '₹');

// Custom Directories
define('UPLOAD_DIR', 'uploads/');

// Session Settings
define('SESSION_LIFETIME', 120);
define('SESSION_NAME', 'POS_SESS');
define('SESSION_DRIVER', 'file');

// Payment Mode Configuration
define('PAYMENT_MODE_CASH', 1); 
define('PAYMENT_MODE_CREDIT', 2); 
define('PAYMENT_MODE_DEBIT', 3); 

// Establish DB Connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
