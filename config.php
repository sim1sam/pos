<?php

// config.php

// Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'pos');

// App settings
define('APP_NAME', 'POS System');
define('APP_URL', 'http://pos.test');
define('APP_ENV', 'local');
define('APP_TIMEZONE', 'Asia/Kolkata');
define('APP_LOCALE', 'en');

// ✅ BASE URL CONSTANT
define('BASE_URL', ''); // For linking pages, assets, etc.

// ✅ MAIL SETTINGS
define('MAIL_HOST', 'smtp.hostinger.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'invoice@wisedynamic.in'); // ✅ Corrected

define('MAIL_PASSWORD', 'Kolk@ta2020');
define('MAIL_FROM_ADDRESS', 'invoice@wisedynamic.in');
define('MAIL_FROM_NAME', 'Invoice');

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

// ✅ Establish DB Connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
