<?php
require_once 'php/config.php';

try {
    $conn = getDBConnection();
    echo "Success! PHP is talking to the Database.";

    $result = $conn->query("SHOW TABLES LIKE 'customers'");
    if ($result->num_rows > 0) {
        echo "<br> 'customers' table found.";
    } else {
        echo "<br>'customers' table MISSING. Please run your SQL schema.";
    }

    closeDBConnection($conn);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>