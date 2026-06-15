<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = ? AND is_admin = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | CompilerHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            min-height: 100vh;
        }
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(17,34,64,0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(0,255,157,0.3);
            box-shadow: 0 0 30px rgba(0,255,157,0.1);
        }
        .login-card h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #00ff9d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #64ffda;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            background: #0a192f;
            border: 1px solid rgba(100,255,218,0.2);
            border-radius: 8px;
            color: #e6f1ff;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #00ff9d, #6c63ff);
            border: none;
            border-radius: 8px;
            color: #0a192f;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px #00ff9d;
        }
        .error-msg {
            background: rgba(255,107,107,0.2);
            border-left: 4px solid #ff6b6b;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <h2><i class="fas fa-lock"></i> Admin Login</h2>
        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="input-group">
                <label><i class="fas fa-key"></i> Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
    </div>
</div>
</body>
</html>