<?php
require_once 'includes/auth.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username/email or password.';
    }
}
$pageTitle = 'Login - CompilerHub';
include 'includes/header.php';
?>
<div class="auth-container" style="max-width: 450px; margin: 60px auto; background: rgba(17,34,64,0.7); padding: 40px; border-radius: 20px; border: 1px solid rgba(0,255,157,0.2);">
    <h2 style="color: #00ff9d; text-align: center; margin-bottom: 30px;">Login to CompilerHub</h2>
    <?php if ($error): ?>
        <div style="background: rgba(255,107,107,0.2); border-left: 4px solid #ff6b6b; padding: 12px; margin-bottom: 20px; border-radius: 6px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <div style="margin-bottom: 20px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-user"></i> Username or Email</label>
            <input type="text" name="username" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <div style="margin-bottom: 25px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <button type="submit" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #00ff9d, #6c63ff); border: none; border-radius: 8px; color: #0a192f; font-weight: bold; font-size: 1rem; cursor: pointer;">Login</button>
    </form>
    <p style="text-align: center; margin-top: 20px; color: #8892b0;">Don't have an account? <a href="register.php" style="color: #00ff9d;">Register here</a></p>
</div>
<?php include 'includes/footer.php'; ?>