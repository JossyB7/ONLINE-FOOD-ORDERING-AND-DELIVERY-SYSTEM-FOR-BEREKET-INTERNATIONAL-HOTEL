<?php
session_start();
require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$orderId) {
    header('Location: orders.php');
    exit;
}

$conn = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $allowed = ['pending', 'verified', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
    
    if (in_array($newStatus, $allowed)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();
        header('Location: order_details.php?id=' . $orderId . '&success=1');
        exit;
    }
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

// Get order items
$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$items = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Bereket Hotel</h2><p>Admin Panel</p></div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">📊 Dashboard</a>
                <a href="orders.php" class="nav-item active">📦 Orders</a>
                <a href="menu.php" class="nav-item">🍽️ Menu</a>
                <a href="users.php" class="nav-item">👥 Users</a>
                <a href="analytics.php" class="nav-item">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1>Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                <a href="orders.php" class="btn-secondary">← Back to Orders</a>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Order status updated successfully!</div>
            <?php endif; ?>

            <div class="order-details-grid">
                <div class="detail-card">
                    <h2>Customer Information</h2>
                    <div class="detail-item">
                        <strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?>
                    </div>
                    <?php if (!empty($order['order_notes'])): ?>
                    <div class="detail-item">
                        <strong>Notes:</strong> <?php echo htmlspecialchars($order['order_notes']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="detail-card">
                    <h2>Order Summary</h2>
                    <div class="detail-item">
                        <strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <strong>Subtotal:</strong> ETB <?php echo number_format($order['subtotal'], 2); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Delivery Fee:</strong> ETB <?php echo number_format($order['delivery_fee'], 2); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Total:</strong> ETB <?php echo number_format($order['total'], 2); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <h2>Order Items</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo (int)$item['quantity']; ?></td>
                                <td>ETB <?php echo number_format($item['item_price'], 2); ?></td>
                                <td>ETB <?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Grand Total</th>
                                <th>ETB <?php echo number_format($order['total'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php if (!empty($order['payment_screenshot'])): ?>
            <div class="content-section">
                <h2>Payment Screenshot</h2>
                <img src="../uploads/payments/<?php echo htmlspecialchars($order['payment_screenshot']); ?>" 
                     alt="Payment Screenshot" class="payment-screenshot">
            </div>
            <?php endif; ?>

            <div class="content-section">
                <h2>Update Order Status</h2>
                <form method="POST" class="status-form">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-group">
                            <?php 
                            $statuses = ['pending', 'verified', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
                            foreach ($statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($order['status'] === $s) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $s)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="update_status" value="1" class="btn-primary">Update Status</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
