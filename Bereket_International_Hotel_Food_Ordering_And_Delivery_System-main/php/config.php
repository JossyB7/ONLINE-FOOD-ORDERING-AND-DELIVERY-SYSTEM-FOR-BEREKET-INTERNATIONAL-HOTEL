<?php
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root'); 
if (!defined('DB_PASS')) define('DB_PASS', ''); 
if (!defined('DB_NAME')) define('DB_NAME', 'bereket_hotel_db');

define('CBE_ACCOUNT_NUMBER', '1000123456789');
define('DELIVERY_FEE', 80.00);
define('FREE_DELIVERY_THRESHOLD', 1000.00);
define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        die("System is currently undergoing maintenance. Please try again later.");
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function closeDBConnection($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

function sanitizeInput($value) {
    if ($value === null) return '';
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function calculateDelivery($subtotal) {
    return ($subtotal >= FREE_DELIVERY_THRESHOLD) ? 0 : DELIVERY_FEE;
}