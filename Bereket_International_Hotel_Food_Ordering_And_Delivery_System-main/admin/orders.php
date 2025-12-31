<?php
session_start();
require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$conn = getDBConnection();
// Handle status update from the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $_POST['update_status'] == '1') {
    $postOrderId = intval($_POST['order_id'] ?? 0);
    $newStatus = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
    if ($postOrderId && $newStatus !== '') {
        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $upd->bind_param('si', $newStatus, $postOrderId);
        $upd->execute();
        $upd->close();
        header('Location: orders.php?id=' . $postOrderId);
        exit;
    }
}

$order = null;
if ($orderId > 0) {
    // 1. Fetch Order General Info
    $orderQuery = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $orderQuery->bind_param('i', $orderId);
    $orderQuery->execute();
    $orderResult = $orderQuery->get_result();
    $order = $orderResult->fetch_assoc();

    if (!$order) {
        die("Order not found.");
    }

    // 2. Fetch Order Items (The food items in this specific order)
    $itemsQuery = $conn->prepare("SELECT oi.*, oi.item_name, oi.item_price as price FROM order_items oi WHERE oi.order_id = ?");
    $itemsQuery->bind_param('i', $orderId);
    $itemsQuery->execute();
    $itemsResult = $itemsQuery->get_result();

} else {
    // List all orders when no specific ID provided
    $listQuery = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
    $ordersList = [];
    if ($listQuery) {
        while ($r = $listQuery->fetch_assoc()) { $ordersList[] = $r; }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .detail-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status-update-form { display: flex; gap: 10px; align-items: center; }
        .orders-table th, .orders-table td { padding: 8px 10px; }
        /* Responsive admin layout */
        .admin-container { display:flex; min-height:100vh; }
        .sidebar { width:240px; flex:0 0 240px; }
        .main-content { flex:1; padding:20px; }
        .sidebar-toggle { display:none; }
        @media (max-width:900px){
            .sidebar{ position:fixed; left:-260px; top:0; bottom:0; z-index:200; background:#111; color:#fff; transition:left .25s ease; }
            .sidebar.open{ left:0; }
            .sidebar-toggle{ display:inline-block; background:#e67e22; color:#fff; border:none; padding:8px 12px; border-radius:6px; }
            .orders-table{ display:block; overflow:auto; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Bereket Hotel</h2><p>Admin Panel</p></div>
            <div style="padding:0 1rem 1rem 1rem;">
                <button id="close-sidebar" class="sidebar-toggle" style="display:none;">Close</button>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">📊 Dashboard</a>
                <a href="orders.php" class="nav-item active">📦 Orders</a>
                <a href="menu.php" class="nav-item">🍽️ Menu Management</a>
                <a href="users.php" class="nav-item">👥 Users</a>
                <a href="analytics.php" class="nav-item">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:1rem;">
                <div style="display:flex;gap:12px;align-items:center;">
                    <button id="sidebar-toggle" class="sidebar-toggle">☰</button>
                    <h1 style="margin:0;">Orders</h1>
                </div>
                <div>
                    <input id="orders-search" placeholder="Search orders..." style="padding:6px;border-radius:4px;border:1px solid #ccc;">
                </div>
            </header>

            <?php if ($orderId > 0 && $order): ?>

                <h1>Order #<?php echo htmlspecialchars($order['order_number'] ?? $orderId); ?></h1>

                <div class="detail-card">
                    <h3>Customer Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? ''); ?></p>
                </div>

                <div class="detail-card">
                    <h3>Order Items</h3>
                    <table width="100%" border="1" class="orders-table" style="border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr style="background:#f4f4f4;">
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($itemsResult) && $itemsResult->num_rows > 0): while($item = $itemsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo (int)$item['quantity']; ?></td>
                                <td>ETB <?php echo number_format($item['price'], 2); ?></td>
                                <td>ETB <?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4">No items found for this order.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Grand Total</th>
                                <th>ETB <?php echo number_format($order['total'] ?? 0, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="detail-card">
                    <h3>Update Order Status</h3>
                    <form action="orders.php" method="POST" class="status-update-form">
                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                        <input type="hidden" name="update_status" value="1">
                        <select name="status">
                            <?php $statuses = ['pending','verified','preparing','out_for_delivery','delivered','cancelled'];
                            foreach ($statuses as $s): ?>
                                <option value="<?php echo $s;?>" <?php if(($order['status'] ?? '')===$s) echo 'selected'; ?>><?php echo ucfirst(str_replace('_',' ',$s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Update Status</button>
                    </form>
                </div>

            <?php else: ?>

                <h1>Orders</h1>
                <div class="detail-card">
                    <table id="orders-table" width="100%" border="1" class="orders-table" style="border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr style="background:#f4f4f4;">
                                <th>ID</th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ordersList)): foreach ($ordersList as $o): ?>
                            <tr>
                                <td class="col-id"><?php echo (int)$o['id']; ?></td>
                                <td class="col-number"><?php echo htmlspecialchars($o['order_number'] ?? ''); ?></td>
                                <td class="col-customer"><?php echo htmlspecialchars($o['customer_name'] ?? ''); ?></td>
                                <td class="col-total">ETB <?php echo number_format($o['total'] ?? 0, 2); ?></td>
                                <td class="col-status">
                                    <span class="status-badge status-<?php echo htmlspecialchars($o['status'] ?? 'pending'); ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $o['status'] ?? 'pending')); ?>
                                    </span>
                                </td>
                                <td class="col-created"><?php echo htmlspecialchars($o['created_at'] ?? ''); ?></td>
                                <td>
                                    <a href="order_details.php?id=<?php echo (int)$o['id']; ?>" class="btn-small">View</a>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="7">No orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

    <script>
    // Orders page interactive behavior: search and AJAX detail modal
    document.addEventListener('DOMContentLoaded', function(){
        const search = document.getElementById('orders-search');
        if (search){
            search.addEventListener('input', function(){
                const q = this.value.toLowerCase();
                document.querySelectorAll('#orders-table tbody tr').forEach(tr=>{
                    const text = tr.innerText.toLowerCase();
                    tr.style.display = text.indexOf(q) === -1 ? 'none' : '';
                });
            });
        }

        document.querySelectorAll('.view-order').forEach(btn=>{
            btn.addEventListener('click', async function(e){
                e.preventDefault();
                const id = this.dataset.id;
                try{
                    const res = await fetch('order_api.php?action=get&id=' + encodeURIComponent(id));
                    const json = await res.json();
                    if (!json.success) { alert('Error: ' + (json.error||'Unknown')); return; }
                    showOrderModal(json.order, json.items);
                }catch(err){ alert('Failed to fetch order'); }
            });
        });
    });

    // sidebar toggle for small screens (shared behavior)
    (function(){
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const closeBtn = document.getElementById('close-sidebar');
        if (!toggle || !sidebar) return;
        toggle.addEventListener('click', ()=> sidebar.classList.toggle('open'));
        if (closeBtn) closeBtn.addEventListener('click', ()=> sidebar.classList.remove('open'));
        document.addEventListener('click', (e)=>{
            if (window.innerWidth <= 900 && sidebar.classList.contains('open')){
                if (!sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
            }
        });
    })();

    function showOrderModal(order, items){
        // build and show a simple modal
        let modal = document.getElementById('order-modal');
        if (!modal){
            modal = document.createElement('div'); modal.id='order-modal';
            modal.style.position='fixed'; modal.style.left=0; modal.style.top=0; modal.style.right=0; modal.style.bottom=0;
            modal.style.background='rgba(0,0,0,0.6)'; modal.style.display='flex'; modal.style.alignItems='center'; modal.style.justifyContent='center';
            modal.innerHTML = '<div style="background:#fff;padding:20px;max-width:800px;width:90%;border-radius:8px;max-height:80vh;overflow:auto;position:relative;">'+
                '<span id="close-order-modal" style="position:absolute;right:15px;top:15px;font-size:28px;cursor:pointer;color:#aaa;">&times;</span><div id="order-modal-content"></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('#close-order-modal').addEventListener('click',()=> modal.remove());
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });
        }
        const content = modal.querySelector('#order-modal-content');
        let html = '<h2>Order #' + (order.order_number || order.id) + '</h2>';
        html += '<p><strong>Customer:</strong> ' + (order.customer_name || '') + '</p>';
        html += '<p><strong>Phone:</strong> ' + (order.customer_phone || '') + '</p>';
        html += '<p><strong>Address:</strong> ' + (order.delivery_address || '') + '</p>';
        html += '<h3>Items</h3><table style="width:100%;border-collapse:collapse;">';
        html += '<tr style="background:#f4f4f4;"><th>Name</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>';
        let total = 0;
        (items||[]).forEach(it=>{ total += (it.price * it.quantity); html += '<tr><td>'+escapeHtml(it.item_name)+'</td><td>'+it.quantity+'</td><td>ETB '+parseFloat(it.price).toFixed(2)+'</td><td>ETB '+(it.price*it.quantity).toFixed(2)+'</td></tr>'; });
        html += '</table>';
        html += '<p><strong>Grand Total:</strong> ETB ' + (order.total||total).toFixed(2) + '</p>';
        html += '<div><label>Status:</label> <select id="modal-status">';
        ['pending','verified','preparing','out_for_delivery','delivered','cancelled'].forEach(s=>{ html += '<option value="'+s+'" ' + ((order.status===s)?'selected':'') + '>' + s.replace(/_/g,' ') + '</option>'; });
        html += '</select> <button id="save-status">Save</button></div>';
        content.innerHTML = html;
        modal.style.display='flex';
        document.getElementById('save-status').addEventListener('click', async function(){
            const status = document.getElementById('modal-status').value;
            try{
                const fd = new FormData(); fd.append('action','update_status'); fd.append('id', order.id); fd.append('status', status);
                const res = await fetch('order_api.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success){ alert('Status updated'); location.reload(); } else alert('Update failed');
            }catch(err){ alert('Request failed'); }
        });
    }

    function escapeHtml(s){ return String(s||'').replace(/[&<>\"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
    </script>
        </main>
    </div>
</body>
</html>

