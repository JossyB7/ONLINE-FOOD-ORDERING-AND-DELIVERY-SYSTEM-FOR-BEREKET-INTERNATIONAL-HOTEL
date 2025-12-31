<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../php/config.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = $_POST['password'] ?? ''; 

    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, full_name, password_hash FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['full_name'];

                header('Location: index.php');
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Username not found.";
        }
        if ($stmt) $stmt->close();
        closeDBConnection($conn);
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bereket International Hotel</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page" style="background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; ">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Bereket International Hotel</h1>
                <p>Admin Login Panel</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="color: red; background: #fee; padding: 10px; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="admin">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-primary btn-block">Login</button>
            </form>
        </div>
    </div>
</body>
</html>