<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    $fullName = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Email is already registered';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO customers (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullName, $email, $phone, $hashedPassword);

            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
    closeDBConnection($conn);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!empty($success)) {
        echo json_encode(['success' => true, 'message' => $success]);
    } else {
        echo json_encode(['success' => false, 'message' => $error ?: 'Registration failed']);
    }
    exit;
}
?>