<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$response = ['success' => true, 'authenticated' => false, 'customer' => null];

if (isset($_SESSION['user_id'])) {
    $response['authenticated'] = true;
    $response['customer'] = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? ''
    ];
}

echo json_encode($response);
exit;
?>