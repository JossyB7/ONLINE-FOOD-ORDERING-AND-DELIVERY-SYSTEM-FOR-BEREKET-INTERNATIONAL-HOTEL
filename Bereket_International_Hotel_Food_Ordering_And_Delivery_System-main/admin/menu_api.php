<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../php/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$action = $_REQUEST['action'] ?? '';
$conn = getDBConnection();

function respond($data, $code=200){ http_response_code($code); echo json_encode($data); exit; }

function saveUploadedImage($fileInputName){
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error']===UPLOAD_ERR_NO_FILE) return ['path'=>'','error'=>null];
    $tmp = $_FILES[$fileInputName]['tmp_name'];
    $type = mime_content_type($tmp);
    $allowed = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];
    if (!isset($allowed[$type])) return ['path'=>'','error'=>'Unsupported image type'];
    $filename = 'menu_' . uniqid() . $allowed[$type];
    $destDir = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'menu';
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
    $dest = $destDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $dest)) return ['path'=>'','error'=>'Failed to move uploaded file'];
    return ['path'=>'uploads/menu/' . $filename, 'error'=>null];
}

try{
    if ($action === 'add'){
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = sanitizeInput($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $imageManual = trim(sanitizeInput($_POST['image'] ?? ''));
        $upload = saveUploadedImage('image_file');
        if ($upload['error']) respond(['success'=>false,'error'=>$upload['error']],400);
        
        // Determine which image to use
        if (!empty($upload['path'])) {
            $image = $upload['path'];
        } elseif (!empty($imageManual)) {
            // Normalize manual image path - remove ../ prefix if present
            $image = preg_replace('#^\.\.?/#', '', $imageManual);
        } else {
            // Use category-specific defaults instead of same image for all items
            $categoryDefaults = [
                'appetizers' => 'asset/image/samosa.jpg',
                'main-course' => 'asset/image/doro.jpg',
                'desserts' => 'asset/image/baklava.jpg',
                'beverages' => 'asset/image/fresh.jpg'
            ];
            $image = $categoryDefaults[$category] ?? 'asset/image/doro.jpg';
        }
        // Default status to 'active' for new items
        $status = 'active';
        $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, category, stock, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdsiss', $name, $description, $price, $category, $stock, $image, $status);
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        respond(['success'=> (bool)$ok, 'action'=>'added', 'id'=>$id, 'row' => ['id'=>$id,'name'=>$name,'price'=>$price,'category'=>$category,'stock'=>$stock,'image'=>$image,'status'=>$status]]);
    }

    if ($action === 'update'){
        $id = intval($_POST['id'] ?? 0);
        if (!$id) respond(['success'=>false,'error'=>'Missing id'],400);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = sanitizeInput($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? 'active');
        $imageManual = trim(sanitizeInput($_POST['image'] ?? ''));
        $upload = saveUploadedImage('image_file');
        if ($upload['error']) respond(['success'=>false,'error'=>$upload['error']],400);
        
        // Determine which image to use
        if (!empty($upload['path'])) {
            $image = $upload['path'];
        } elseif (!empty($imageManual)) {
            // Normalize manual image path - remove ../ prefix if present
            $image = preg_replace('#^\.\.?/#', '', $imageManual);
        } else {
            // Keep existing image from database if no new image provided
            $stmt = $conn->prepare("SELECT image FROM menu_items WHERE id=?");
            $stmt->bind_param('i', $id);
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
                $image = $categoryDefaults[$category] ?? 'asset/image/doro.jpg';
            } else {
                $image = $existing['image'];
            }
        }
        $stmt = $conn->prepare("UPDATE menu_items SET name=?, description=?, price=?, category=?, stock=?, image=?, status=? WHERE id=?");
        $stmt->bind_param('ssdsissi', $name, $description, $price, $category, $stock, $image, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        respond(['success'=>(bool)$ok,'action'=>'updated','id'=>$id]);
    }

    if ($action === 'delete'){
        $id = intval($_POST['id'] ?? 0);
        if (!$id) respond(['success'=>false,'error'=>'Missing id'],400);
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id=?");
        $stmt->bind_param('i',$id);
        $ok = $stmt->execute();
        $stmt->close();
        respond(['success'=>(bool)$ok,'action'=>'deleted','id'=>$id]);
    }

    respond(['success'=>false,'error'=>'Invalid action'],400);

} catch(Exception $e){ respond(['success'=>false,'error'=>$e->getMessage()],500); }

?>
