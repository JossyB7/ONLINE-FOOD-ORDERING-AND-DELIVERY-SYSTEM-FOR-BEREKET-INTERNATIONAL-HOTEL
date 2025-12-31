<?php
session_start();

require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed. Check config.php.");
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $customerId = intval($_POST['customer_id']);
    $newStatus = sanitizeInput($_POST['status']);
    $allowedStatuses = ['active', 'inactive', 'suspended'];

    if (in_array($newStatus, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE customers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $customerId);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: users.php?success=1');
            exit;
        }
        $stmt->close();
    }
}

$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$query = "SELECT * FROM customers";
if ($statusFilter !== 'all') {
    $query .= " WHERE status = '" . $conn->real_escape_string($statusFilter) . "'";
}
$query .= " ORDER BY created_at DESC";

$customers = [];
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Get statistics
$stats = ['total' => 0, 'active' => 0, 'today' => 0];
$res1 = $conn->query("SELECT COUNT(*) as total FROM customers");
if ($res1) $stats['total'] = $res1->fetch_assoc()['total'];
$res2 = $conn->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
if ($res2) $stats['active'] = $res2->fetch_assoc()['total'];
$res3 = $conn->query("SELECT COUNT(*) as total FROM customers WHERE DATE(created_at) = CURDATE()");
if ($res3) $stats['today'] = $res3->fetch_assoc()['total'];

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Bereket Hotel</h2><p>Admin Panel</p></div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">📊 Dashboard</a>
                <a href="orders.php" class="nav-item">📦 Orders</a>
                <a href="menu.php" class="nav-item">🍽️ Menu Management</a>
                <a href="users.php" class="nav-item active">👥 Users</a>
                <a href="analytics.php" class="nav-item">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <div style="display:flex;gap:12px;align-items:center;">
                    <button id="sidebar-toggle" class="sidebar-toggle">☰</button>
                    <h1 style="margin:0;">User Management</h1>
                </div>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">User status updated!</div>
            <?php endif; ?>

            <div class="stats-grid" style="display:flex; gap:20px; margin-bottom:30px;">
                <div class="stat-card" style="background:#f8f9fa; padding:20px; border-radius:8px; flex:1; text-align:center; border-left:5px solid #007bff;">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Customers</p>
                </div>
                <div class="stat-card" style="background:#f8f9fa; padding:20px; border-radius:8px; flex:1; text-align:center; border-left:5px solid #28a745;">
                    <h3><?php echo $stats['active']; ?></h3>
                    <p>Active Users</p>
                </div>
                <div class="stat-card" style="background:#f8f9fa; padding:20px; border-radius:8px; flex:1; text-align:center; border-left:5px solid #ffc107;">
                    <h3><?php echo $stats['today']; ?></h3>
                    <p>New Today</p>
                </div>
            </div>

            <div class="filter-section">
                <a href="?status=all" class="filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?status=active" class="filter-btn <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">Active</a>
                <a href="?status=inactive" class="filter-btn <?php echo $statusFilter === 'inactive' ? 'active' : ''; ?>">Inactive</a>
                <a href="?status=suspended" class="filter-btn <?php echo $statusFilter === 'suspended' ? 'active' : ''; ?>">Suspended</a>
            </div>

            <div class="table-container">
                <table class="data-table" border="1" width="100%" style="border-collapse:collapse; text-align:left;">
                    <thead style="background:#eee;">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="customer_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspend</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
    // Sidebar toggle for mobile
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