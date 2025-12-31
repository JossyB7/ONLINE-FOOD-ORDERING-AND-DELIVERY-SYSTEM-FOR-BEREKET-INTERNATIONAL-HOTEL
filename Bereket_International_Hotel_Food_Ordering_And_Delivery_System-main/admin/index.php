<?php
session_start();
require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed. Please check your config.php settings.");
}

$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
$stats['today_orders'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT SUM(total) as total FROM orders WHERE status NOT IN ('pending', 'cancelled')");
$row = $result ? $result->fetch_assoc() : null;
$stats['total_revenue'] = $row['total'] ?? 0;

$recentOrders = [];
$result = $conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bereket Hotel</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Bereket Hotel</h2>
                <p>Admin Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item active">📊 Dashboard</a>
                <a href="orders.php" class="nav-item">📦 Orders</a>
                <a href="menu.php" class="nav-item">🍽️ Menu Management</a>
                <a href="users.php" class="nav-item">👥 Users</a>
                <a href="analytics.php" class="nav-item">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <div style="display:flex;gap:12px;align-items:center;">
                    <button id="sidebar-toggle" class="sidebar-toggle">☰</button>
                    <h1 style="margin:0;">Business Overview</h1>
                </div>
                <div class="user-info">
                    <strong>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong>
                </div>
            </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #f39c12;">⏳</div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending_orders']; ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #27ae60;">📅</div>
                <div class="stat-info">
                    <h3><?php echo $stats['today_orders']; ?></h3>
                    <p>Today's Sales</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #2c3e50;">💰</div>
                <div class="stat-info">
                    <h3>ETB <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>

            <div class="content-section">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2>Recent Orders</h2>
                    <a href="orders.php" class="btn-secondary">View All →</a>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="6" class="text-center" style="padding:30px;">No orders found yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>ETB <?php echo number_format($order['total'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>
    (function(){
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        if (!toggle || !sidebar) return;
        toggle.addEventListener('click', ()=> sidebar.classList.toggle('open'));
        document.addEventListener('click', (e)=>{
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')){
                if (!sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
            }
        });
    })();
    </script>
</body>
</html>