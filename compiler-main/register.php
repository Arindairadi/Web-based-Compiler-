<?php
require_once 'includes/auth.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if (register($username, $email, $password)) {
            $success = 'Registration successful. You can now login.';
        } else {
            $error = 'Username or email already exists.';
        }
    }
}
$pageTitle = 'Register - CompilerHub';
include 'includes/header.php';
?>
<div class="auth-container" style="max-width: 450px; margin: 60px auto; background: rgba(17,34,64,0.7); padding: 40px; border-radius: 20px; border: 1px solid rgba(0,255,157,0.2);">
    <h2 style="color: #00ff9d; text-align: center; margin-bottom: 30px;">Create an Account</h2>
    <?php if ($error): ?>
        <div style="background: rgba(255,107,107,0.2); border-left: 4px solid #ff6b6b; padding: 12px; margin-bottom: 20px; border-radius: 6px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div style="background: rgba(0,255,157,0.1); border-left: 4px solid #00ff9d; padding: 12px; margin-bottom: 20px; border-radius: 6px;">
            <?php echo htmlspecialchars($success); ?> <a href="login.php" style="color: #00ff9d;">Login now</a>
        </div>
    <?php endif; ?>
    <form method="post">
        <div style="margin-bottom: 20px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-envelope"></i> Email</label>
            <input type="email" name="email" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <div style="margin-bottom: 25px;">
            <label style="color: #64ffda; display: block; margin-bottom: 8px;"><i class="fas fa-check-circle"></i> Confirm Password</label>
            <input type="password" name="confirm_password" required style="width: 100%; padding: 12px; background: #0a192f; border: 1px solid rgba(100,255,218,0.2); border-radius: 8px; color: #e6f1ff;">
        </div>
        <button type="submit" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #00ff9d, #6c63ff); border: none; border-radius: 8px; color: #0a192f; font-weight: bold; font-size: 1rem; cursor: pointer;">Register</button>
    </form>
    <p style="text-align: center; margin-top: 20px; color: #8892b0;">Already have an account? <a href="login.php" style="color: #00ff9d;">Login</a></p>
</div>
<?php include 'includes/footer.php'; ?>