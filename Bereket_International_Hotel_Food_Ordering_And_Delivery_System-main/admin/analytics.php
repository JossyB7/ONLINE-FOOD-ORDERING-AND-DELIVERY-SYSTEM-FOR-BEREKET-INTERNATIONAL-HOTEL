<?php
session_start();

require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}


$analytics = [];

$result = $conn->query("SELECT SUM(total) as total FROM orders WHERE status IN ('verified', 'preparing', 'out_for_delivery', 'delivered')");
$analytics['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

$statusCounts = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}

$popularItems = [];
$result = $conn->query(
    "SELECT oi.item_name, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.status IN ('verified', 'preparing', 'out_for_delivery', 'delivered') GROUP BY oi.item_name ORDER BY total_quantity DESC LIMIT 10"
);
while ($row = $result->fetch_assoc()) {
    $popularItems[] = $row;
}

$dailyRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT SUM(total) as total FROM orders WHERE DATE(created_at) = ? AND status IN ('verified', 'preparing', 'out_for_delivery', 'delivered')");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $dailyRevenue[$date] = $res->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .revenue-header { background: #2c3e50; color: white; padding: 40px; border-radius: 10px; margin-bottom: 30px; text-align: center; }
        .revenue-amount { font-size: 3rem; font-weight: bold; display: block; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .analytics-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .bar-container { background: #eee; height: 20px; border-radius: 10px; overflow: hidden; margin-top: 5px; }
        .bar-fill { background: #3498db; height: 100%; }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Bereket Hotel</h2>
                <p>Admin Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">📊 Dashboard</a>
                <a href="orders.php" class="nav-item">📦 Orders</a>
                <a href="menu.php" class="nav-item">🍽️ Menu Management</a>
                <a href="users.php" class="nav-item">👥 Users</a>
                <a href="analytics.php" class="nav-item active">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1>Analytics & Insights</h1>
            </header>

            <div class="revenue-header">
                <p>TOTAL LIFETIME REVENUE</p>
                <span class="revenue-amount">ETB <?php echo number_format($analytics['total_revenue'], 2); ?></span>
            </div>

            <div class="grid-2">
                <div class="analytics-card">
                    <h2>Orders by Status</h2>
                    
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($statusCounts as $status => $count): ?>
                            <li style="margin-bottom: 10px;">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $status)); ?>:</strong> <?php echo $count; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="analytics-card">
                    <h2>Last 7 Days Revenue</h2>
                    
                    <table width="100%">
                        <?php foreach ($dailyRevenue as $date => $rev): ?>
                            <tr>
                                <td style="padding: 5px 0;"><?php echo date('D, M d', strtotime($date)); ?></td>
                                <td style="text-align: right;">ETB <?php echo number_format($rev, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="analytics-card">
                <h2>Top 10 Selling Items</h2>
                <table class="data-table" width="100%" style="border-collapse: collapse;">
                    <thead style="background: #f4f4f4;">
                        <tr>
                            <th style="padding: 12px; text-align: left;">Item Name</th>
                            <th>Quantity Sold</th>
                            <th>Revenue Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popularItems as $item): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td style="text-align: center;"><?php echo $item['total_quantity']; ?></td>
                                <td style="text-align: right;">ETB <?php echo number_format($item['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>