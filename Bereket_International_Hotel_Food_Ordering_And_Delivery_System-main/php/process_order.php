<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to place an order']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get cart from FormData (sent as JSON string)
    $cartJson = $_POST['cart'] ?? '';
    if (empty($cartJson)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }
    
    $cart = json_decode($cartJson, true);
    if (empty($cart) || !is_array($cart)) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart data']);
        exit;
    }

    $conn = getDBConnection();

    // Map form field names to expected names
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerEmail = sanitizeInput($_POST['customer_email'] ?? $_SESSION['user_email'] ?? '');
    $phone = sanitizeInput($_POST['customer_phone'] ?? '');
    $address = sanitizeInput($_POST['delivery_address'] ?? '');
    $notes = sanitizeInput($_POST['order_notes'] ?? '');

    // Validate required fields
    if (empty($customerName) || empty($customerEmail) || empty($phone) || empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        closeDBConnection($conn);
        exit;
    }

    // Get totals from form data
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $deliveryFee = (float)($_POST['delivery_fee'] ?? DELIVERY_FEE);
    $total = (float)($_POST['total'] ?? 0);

    // Validate totals
    if ($total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order total']);
        closeDBConnection($conn);
        exit;
    }

    // Handle payment screenshot upload
    $screenshotName = '';
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === 0) {
        $ext = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowedExts)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image.']);
            closeDBConnection($conn);
            exit;
        }
        
        $screenshotName = "PAY_" . time() . "_" . uniqid() . "." . $ext;
        $targetPath = UPLOAD_PATH . 'payments' . DIRECTORY_SEPARATOR . $screenshotName;
        
        // Create directory if it doesn't exist
        $uploadDir = dirname($targetPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload payment screenshot']);
            closeDBConnection($conn);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment screenshot is required']);
        closeDBConnection($conn);
        exit;
    }

    $orderNumber = "BRK-" . strtoupper(substr(uniqid(), -8));

    $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, delivery_address, order_notes, subtotal, delivery_fee, total, payment_screenshot, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    $stmt->bind_param("ssssssddds", $orderNumber, $customerName, $customerEmail, $phone, $address, $notes, $subtotal, $deliveryFee, $total, $screenshotName);

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, item_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");

        foreach ($cart as $item) {
            $itemSub = (float)$item['price'] * (int)$item['quantity'];
            $itemStmt->bind_param("isdid", $orderId, $item['name'], $item['price'], $item['quantity'], $itemSub);
            if (!$itemStmt->execute()) {
                $stmt->close();
                $itemStmt->close();
                closeDBConnection($conn);
                echo json_encode(['success' => false, 'message' => 'Failed to save order items: ' . $conn->error]);
                exit;
            }
        }

        $stmt->close();
        $itemStmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_number' => $orderNumber]);
        exit;
    } else {
        $stmt->close();
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'message' => 'Order Error: ' . $conn->error]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}