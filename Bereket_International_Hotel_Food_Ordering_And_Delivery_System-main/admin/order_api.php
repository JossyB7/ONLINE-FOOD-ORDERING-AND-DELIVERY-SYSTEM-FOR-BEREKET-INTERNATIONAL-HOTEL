<?php
session_start();
require_once __DIR__ . '/../php/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$conn = getDBConnection();

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) respond(['success' => false, 'error' => 'Missing id'], 400);
        
        // Get order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$order) respond(['success' => false, 'error' => 'Order not found'], 404);
        
        // Get order items
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $items = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'item_name' => $row['item_name'],
                'quantity' => (int)$row['quantity'],
                'price' => floatval($row['item_price']),
                'subtotal' => floatval($row['subtotal'])
            ];
        }
        $stmt->close();
        
        respond(['success' => true, 'order' => $order, 'items' => $items]);
    }
    
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? '');
        
        if (!$id || !$status) respond(['success' => false, 'error' => 'Missing id or status'], 400);
        
        $allowed = ['pending', 'verified', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed)) respond(['success' => false, 'error' => 'Invalid status'], 400);
        
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        
        respond(['success' => (bool)$ok, 'id' => $id, 'status' => $status]);
    }
    
    respond(['success' => false, 'error' => 'Invalid action'], 400);
} catch (Exception $e) {
    respond(['success' => false, 'error' => $e->getMessage()], 500);
} finally {
    if (isset($conn)) closeDBConnection($conn);
}
?>

