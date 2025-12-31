<?php
session_start();
require_once __DIR__ . '/../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$error = '';

$uploadDir = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'menu';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

function handleMenuImageUpload($currentImage = '') {
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => $currentImage, 'error' => null];
    }
    $allowedTypes = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
    $tmpPath = $_FILES['image_file']['tmp_name'];
    $detectedType = mime_content_type($tmpPath);

    if (!isset($allowedTypes[$detectedType])) {
        return ['path' => $currentImage, 'error' => 'Unsupported type. Use JPG or PNG.'];
    }

    $filename = 'menu_' . uniqid() . $allowedTypes[$detectedType];
    $destination = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'menu' . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($tmpPath, $destination)) {
        return ['path' => 'uploads/menu/' . $filename, 'error' => null];
    }
    return ['path' => $currentImage, 'error' => 'Failed to save image. Check folder permissions.'];
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD ITEM
    if (isset($_POST['add_item']) && $_POST['add_item'] == "1") {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $price = floatval($_POST['price']);
        $category = sanitizeInput($_POST['category']);
        $stock = intval($_POST['stock']);
        $image = sanitizeInput($_POST['image']);
        
        $upload = handleMenuImageUpload();
        if ($upload['error']) { $error = $upload['error']; } 
        else {
            // Determine which image to use
            if (!empty($upload['path'])) {
                $imagePath = $upload['path'];
            } elseif (!empty($image)) {
                // Normalize manual image path - remove ../ prefix if present
                $imagePath = preg_replace('#^\.\.?/#', '', $image);
            } else {
                // Use category-specific defaults instead of same image for all items
                $categoryDefaults = [
                    'appetizers' => 'asset/image/samosa.jpg',
                    'main-course' => 'asset/image/doro.jpg',
                    'desserts' => 'asset/image/baklava.jpg',
                    'beverages' => 'asset/image/fresh.jpg'
                ];
                $imagePath = $categoryDefaults[$category] ?? 'asset/image/doro.jpg';
            }
            // Default status to 'active' for new items
            $status = 'active';
            $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, category, stock, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsiss", $name, $description, $price, $category, $stock, $imagePath, $status);
            $stmt->execute();
            header('Location: menu.php?success=1'); exit;
        }
    }
    
    if (isset($_POST['update_item']) && $_POST['update_item'] == "1") {
        $id = intval($_POST['id']);
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $price = floatval($_POST['price']);
        $category = sanitizeInput($_POST['category']);
        $stock = intval($_POST['stock']);
        $status = sanitizeInput($_POST['status']);
        
        $upload = handleMenuImageUpload($_POST['image']);
        // Determine which image to use
        if (!empty($upload['path'])) {
            $imagePath = $upload['path'];
        } elseif (!empty($_POST['image'])) {
            // Normalize manual image path - remove ../ prefix if present
            $imagePath = preg_replace('#^\.\.?/#', '', $_POST['image']);
        } else {
            // Keep existing image from database if no new image provided
            $stmt = $conn->prepare("SELECT image FROM menu_items WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            // Use category-specific default if existing image is empty
            if (empty($existing['image']) || trim($existing['image']) === '' || trim($existing['image']) === 'null') {
                $categoryDefaults = [
                    'appetizers' => 'asset/image/samosa.jpg',
                    'main-course' => 'asset/image/doro.jpg',
                    'desserts' => 'asset/image/baklava.jpg',
                    'beverages' => 'asset/image/fresh.jpg'
                ];
                $imagePath = $categoryDefaults[$category] ?? 'asset/image/doro.jpg';
            } else {
                $imagePath = $existing['image'];
            }
        }

        $stmt = $conn->prepare("UPDATE menu_items SET name=?, description=?, price=?, category=?, stock=?, image=?, status=? WHERE id=?");
        $stmt->bind_param("ssdsissi", $name, $description, $price, $category, $stock, $imagePath, $status, $id);
        $stmt->execute();
        header('Location: menu.php?success=1'); exit;
    }

    // DELETE ITEM
    if (isset($_POST['delete_item'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header('Location: menu.php?success=1'); exit;
    }
}

$items = [];
$result = $conn->query("SELECT * FROM menu_items ORDER BY category, name");
if ($result) {
    while ($row = $result->fetch_assoc()) { $items[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .modal { position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display:none; }
        .modal-content { background: white; margin: 5% auto; padding: 20px; width: 50%; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-success { background: #d4edda; color: #155724; }

        .admin-container { display: flex; min-height: 100vh; }
        .sidebar { width: 240px; flex: 0 0 240px; }
        .main-content { flex: 1; padding: 20px; }

        .sidebar-toggle { display: none; 
            background: #e67e22; 
            color: #fff; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 6px; 
        }

        @media (max-width: 900px) {
            .admin-container { flex-direction: row; }
            .sidebar { position: fixed; left: -260px; top: 0; bottom: 0; z-index: 200; background: #111; color: #fff; transition: left 0.25s ease; }
            .sidebar.open { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar-toggle { display: inline-block; }
            .table-container { overflow-x: auto; }
            .modal-content { width: 95%; margin: 6vh auto; }
            .data-table img { width: 48px; }
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
                <a href="orders.php" class="nav-item">📦 Orders</a>
                <a href="menu.php" class="nav-item active">🍽️ Menu Management</a>
                <a href="users.php" class="nav-item">👥 Users</a>
                <a href="analytics.php" class="nav-item">📈 Analytics</a>
                <a href="logout.php" class="nav-item">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <div style="display:flex;gap:12px;align-items:center;">
                    <button id="sidebar-toggle" class="sidebar-toggle">☰</button>
                    <h1 style="margin:0;">Menu Management</h1>
                </div>
                <div>
                    <button onclick="openAddModal()" class="btn-primary">+ Add Item</button>
                </div>
            </header>

            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Operation successful!</div><?php endif; ?>

            <div class="table-container">
                <table class="data-table" border="1" width="100%" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Stock</th><th>Image</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>ETB <?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo ucfirst($item['category']); ?></td>
                            <td><?php echo $item['stock']; ?></td>
                            <td>
                                <?php 
                                $imagePath = $item['image'];
                                // Normalize path - remove ../ prefix if present
                                $imagePath = preg_replace('#^\.\.?/#', '', $imagePath);
                                
                                if(strpos($imagePath, 'uploads/') === 0): 
                                    // Uploaded image - needs ../ prefix for admin panel
                                    echo '<img src="../' . htmlspecialchars($imagePath) . '" width="40" alt="' . htmlspecialchars($item['name']) . '" onerror="this.src=\'../asset/image/doro.jpg\'">';
                                elseif(strpos($imagePath, 'asset/') === 0): 
                                    // Asset folder image - needs ../ prefix for admin panel
                                    echo '<img src="../' . htmlspecialchars($imagePath) . '" width="40" alt="' . htmlspecialchars($item['name']) . '" onerror="this.src=\'../asset/image/doro.jpg\'">';
                                elseif(preg_match('#^(https?://|data:)#', $imagePath)): 
                                    // Full URL or data URI
                                    echo '<img src="' . htmlspecialchars($imagePath) . '" width="40" alt="' . htmlspecialchars($item['name']) . '" onerror="this.src=\'../asset/image/default.jpg\'">';
                                else: 
                                    // Other cases (emoji, invalid path, etc.) - show as text or default
                                    if (strlen($imagePath) < 20 && !strpos($imagePath, '.')) {
                                        echo htmlspecialchars($imagePath);
                                    } else {
                                        echo '<img src="../asset/image/doro.jpg" width="40" alt="' . htmlspecialchars($item['name']) . '">';
                                    }
                                endif; 
                                ?>
                            </td>
                            <td>
                                <button onclick='editItem(<?php echo json_encode($item); ?>)'>Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="delete_item" value="1">
                                    <button type="submit" style="color:red;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="add-item-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modal-title">Add Menu Item</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="item-id">
                <input type="hidden" name="add_item" id="is-add" value="1">
                <input type="hidden" name="update_item" id="is-update" value="0">
                
                <div class="form-group"><label>Name *</label><input type="text" id="name" name="name" required></div>
                <div class="form-group"><label>Description</label><textarea id="description" name="description"></textarea></div>
                <div class="form-group"><label>Price (ETB) *</label><input type="number" id="price" name="price" step="0.01" required></div>
                <div class="form-group">
                    <label>Category *</label>
                    <select id="category" name="category" required>
                        <option value="appetizers">Appetizers</option>
                        <option value="main-course">Main Course</option>
                        <option value="desserts">Desserts</option>
                        <option value="beverages">Beverages</option>
                    </select>
                </div>
                <div class="form-group"><label>Stock *</label><input type="number" id="stock" name="stock" required></div>
                <div class="form-group"><label>Manual Image (Emoji/URL)</label><input type="text" id="image" name="image"></div>
                <div class="form-group"><label>Upload Image File</label><input type="file" name="image_file"></div>
                
                <div id="status-group" class="form-group" style="display:none;">
                    <label>Status</label>
                    <select id="status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit">Save Item</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modal-title').innerText = 'Add Menu Item';
            document.getElementById('is-add').value = '1';
            document.getElementById('is-update').value = '0';
            document.getElementById('status-group').style.display = 'none';
            document.getElementById('add-item-modal').style.display = 'block';
        }

        function editItem(item) {
            document.getElementById('modal-title').innerText = 'Edit Menu Item';
            document.getElementById('item-id').value = item.id;
            document.getElementById('is-add').value = '0';
            document.getElementById('is-update').value = '1';
            document.getElementById('name').value = item.name;
            document.getElementById('description').value = item.description;
            document.getElementById('price').value = item.price;
            document.getElementById('category').value = item.category;
            document.getElementById('stock').value = item.stock;
            document.getElementById('image').value = item.image;
            document.getElementById('status').value = item.status;
            document.getElementById('status-group').style.display = 'block';
            document.getElementById('add-item-modal').style.display = 'block';
        }

        function closeModal() { 
            document.getElementById('add-item-modal').style.display = 'none';
            document.querySelector('#add-item-modal form').reset();
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('add-item-modal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
    <script>
    (function(){
        const form = document.querySelector('#add-item-modal form');
        if (form) {
            form.addEventListener('submit', async function(e){
                e.preventDefault();
                const formData = new FormData(form);
                const isAdd = document.getElementById('is-add').value === '1';
                formData.append('action', isAdd ? 'add' : 'update');
                try{
                    const res = await fetch('menu_api.php', { method:'POST', body: formData });
                    const json = await res.json();
                    if (json.success){
                        alert('Saved successfully');
                        location.reload();
                    } else { alert('Error: ' + (json.error||'Unknown')); }
                }catch(err){ alert('Request failed'); }
            });
        }

        document.querySelectorAll('form[onsubmit]').forEach(f => {
            f.addEventListener('submit', async function(e){
                e.preventDefault();
                if (!confirm('Delete this item?')) return;
                const id = this.querySelector('input[name="id"]').value;
                const fd = new FormData(); 
                fd.append('action','delete'); 
                fd.append('id', id);
                try{
                    const res = await fetch('menu_api.php', { method:'POST', body: fd });
                    const json = await res.json();
                    if (json.success){ 
                        location.reload(); 
                    } else { 
                        alert('Error deleting: ' + (json.error||'Unknown')); 
                    }
                }catch(err){ alert('Delete request failed'); }
            });
        });
    })();
    </script>
    <script>
    (function(){
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const closeBtn = document.getElementById('close-sidebar');
        if (!toggle || !sidebar) return;
        toggle.addEventListener('click', ()=> sidebar.classList.toggle('open'));
        if (closeBtn) closeBtn.addEventListener('click', ()=> sidebar.classList.remove('open'));
        // Close sidebar when clicking outside on small screens
        document.addEventListener('click', (e)=>{
            if (window.innerWidth <= 900 && sidebar.classList.contains('open')){
                if (!sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
            }
        });
    })();
    </script>
</body>
</html>
